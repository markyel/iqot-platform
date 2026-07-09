"""
Universal Email Service - Email Sender
SMTP operations without any database or configuration
"""

import smtplib
import ssl
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.mime.base import MIMEBase
from email import encoders
from email.header import Header
from email.utils import formataddr, formatdate, make_msgid
import base64
from datetime import datetime
from typing import Optional, Dict, Any
import logging
import time

from models import SendEmailRequest, EmailAttachment

logger = logging.getLogger(__name__)

class EmailSender:
    """Handles sending emails via SMTP - stateless"""
    
    def __init__(self):
        logger.info("EmailSender initialized (stateless)")
    
    def _ssl_context(self, verify_cert: bool):
        """
        SSL-контекст для SMTP. verify_cert=False → не проверять hostname (провайдеры с
        общим сертификатом, напр. sprinthost CN=from.sh на smtp.wwwsend.ru), CA-валидность
        цепочки при этом сохраняется. Зеркало логики Symfony Mailer (verify_peer_name=false).
        """
        context = ssl.create_default_context()
        if not verify_cert:
            context.check_hostname = False
        return context

    def _get_smtp_connection(
        self,
        server: str,
        port: int,
        user: str,
        password: str,
        encryption: str,
        timeout: int,
        verify_cert: bool = True
    ):
        """
        Создать SMTP подключение

        Args:
            server: SMTP сервер
            port: SMTP порт
            user: SMTP пользователь
            password: SMTP пароль
            encryption: Тип шифрования (ssl, tls, none)
            timeout: Таймаут
            verify_cert: Проверять hostname TLS-сертификата

        Returns:
            smtplib.SMTP: SMTP подключение
        """
        try:
            encryption = encryption.lower()

            if encryption == 'ssl':
                smtp = smtplib.SMTP_SSL(server, port, timeout=timeout, context=self._ssl_context(verify_cert))
            else:
                smtp = smtplib.SMTP(server, port, timeout=timeout)
                if encryption == 'tls':
                    smtp.starttls(context=self._ssl_context(verify_cert))

            # Авторизация
            smtp.login(user, password)
            
            logger.info(f"SMTP connected: {server}:{port} (encryption={encryption})")
            return smtp
            
        except Exception as e:
            logger.error(f"Failed to connect to SMTP: {e}")
            raise
    
    def _create_message(
        self,
        from_email: str,
        from_name: Optional[str],
        to_email: str,
        subject: str,
        body_html: str,
        body_text: Optional[str],
        attachments: Optional[list],
        reply_to: Optional[str],
        cc: Optional[list],
        bcc: Optional[list],
        message_id: Optional[str] = None,
        in_reply_to: Optional[str] = None,
        references: Optional[str] = None
    ) -> MIMEMultipart:
        """
        Создать MIME сообщение
        
        Args:
            from_email: Email отправителя
            from_name: Имя отправителя
            to_email: Email получателя
            subject: Тема
            body_html: HTML тело
            body_text: Текстовое тело
            attachments: Вложения
            reply_to: Reply-To адрес
            cc: Копия
            bcc: Скрытая копия
            
        Returns:
            MIMEMultipart: MIME сообщение
        """
        # СТРУКТУРА MIME:
        #   без вложений → multipart/alternative (text + html)
        #   с вложениями → multipart/mixed [ multipart/alternative (text+html), вложения ]
        # РАНЬШЕ вложения клались прямо в 'alternative' — битая структура (вложение как
        # «альтернатива» телу), почтовики её штрафуют. Теперь корректно.
        alt = MIMEMultipart('alternative')
        # Текстовая версия ПЕРВОЙ (порядок alternative = от простого к богатому).
        if body_text:
            alt.attach(MIMEText(body_text, 'plain', 'utf-8'))
        alt.attach(MIMEText(body_html, 'html', 'utf-8'))

        if attachments:
            msg = MIMEMultipart('mixed')
            msg.attach(alt)
        else:
            msg = alt

        # Заголовок From - правильное кодирование
        # Используем formataddr для корректного формирования заголовка:
        # имя кодируется отдельно, email остаётся в угловых скобках без кодирования
        if from_name:
            # formataddr правильно обрабатывает Unicode в имени
            msg['From'] = formataddr((str(Header(from_name, 'utf-8')), from_email))
        else:
            msg['From'] = from_email

        msg['To'] = to_email
        # Тема: срезаем CR/LF — иначе перенос строки инъектит второй заголовок Subject
        # (gmail: «multiple Subject headers»).
        clean_subject = (subject or '').replace('\r', ' ').replace('\n', ' ').strip()
        msg['Subject'] = Header(clean_subject, 'utf-8')
        # Date СТРОГО с таймзоной (RFC 5322). Раньше был naive datetime + '%z' → пустой
        # offset («Date: … 18:40:00 » без +0300), почтовики штрафуют. formatdate даёт
        # корректный «Fri, 09 Jul 2026 18:40:00 +0300».
        msg['Date'] = formatdate(localtime=True)

        # Message-ID: передан Laravel → ставим; иначе ГЕНЕРИМ (письмо без Message-ID
        # gmail отклоняет, у mail.ru/Яндекса — спам-очки). Домен из адреса отправителя.
        if not message_id:
            try:
                domain = from_email.split('@', 1)[1] if '@' in from_email else 'localhost'
            except Exception:
                domain = 'localhost'
            message_id = make_msgid(domain=domain)
        msg['Message-ID'] = message_id

        # Threading ответов — значения уже с угловыми скобками, ставим как есть.
        if in_reply_to:
            msg['In-Reply-To'] = in_reply_to
        if references:
            msg['References'] = references

        if reply_to:
            msg['Reply-To'] = reply_to

        if cc:
            msg['Cc'] = ', '.join(cc)

        # BCC не добавляется в заголовки (это скрытая копия)

        # Вложения — в multipart/mixed корень (НЕ в alternative).
        if attachments:
            for attachment in attachments:
                part = MIMEBase('application', 'octet-stream')

                # Декодируем base64
                content = base64.b64decode(attachment.content)
                part.set_payload(content)
                encoders.encode_base64(part)

                part.add_header(
                    'Content-Disposition',
                    f'attachment; filename={attachment.filename}'
                )

                if attachment.content_type:
                    part.add_header('Content-Type', attachment.content_type)

                msg.attach(part)

        return msg
    
    async def send_email(self, request: SendEmailRequest) -> Dict[str, Any]:
        """
        Отправить email
        
        Args:
            request: Параметры отправки
            
        Returns:
            Dict: Результат отправки
        """
        retry_count = 0
        last_error = None
        
        # Создаём сообщение
        msg = self._create_message(
            from_email=request.from_email,
            from_name=request.from_name,
            to_email=request.to_email,
            subject=request.subject,
            body_html=request.body_html,
            body_text=request.body_text,
            attachments=request.attachments,
            reply_to=request.reply_to,
            cc=request.cc,
            bcc=request.bcc,
            message_id=request.message_id,
            in_reply_to=request.in_reply_to,
            references=request.references
        )
        
        # Получаем всех получателей (для send_message)
        recipients = [request.to_email]
        if request.cc:
            recipients.extend(request.cc)
        if request.bcc:
            recipients.extend(request.bcc)
        
        # Попытки отправки
        while retry_count < request.retry_attempts:
            try:
                smtp = self._get_smtp_connection(
                    server=request.smtp_server,
                    port=request.smtp_port,
                    user=request.smtp_user,
                    password=request.smtp_password,
                    encryption=request.smtp_encryption,
                    timeout=request.timeout,
                    verify_cert=request.verify_cert
                )

                smtp.send_message(msg)
                smtp.quit()
                
                logger.info(f"Email sent: from={request.from_email}, to={request.to_email}")
                
                return {
                    'success': True,
                    'message_id': msg.get('Message-ID'),
                    'from': request.from_email,
                    'to': request.to_email,
                    'subject': request.subject,
                    'sent_at': datetime.now().isoformat(),
                    'attempts': retry_count + 1
                }
                
            except Exception as e:
                last_error = str(e)
                retry_count += 1
                logger.warning(f"Send attempt {retry_count} failed: {e}")
                
                if retry_count < request.retry_attempts:
                    time.sleep(request.retry_delay)
        
        # Все попытки не удались
        error_msg = f"Failed after {request.retry_attempts} attempts: {last_error}"
        logger.error(error_msg)
        raise Exception(error_msg)
    
    async def test_connection(
        self,
        smtp_server: str,
        smtp_port: int,
        smtp_user: str,
        smtp_password: str,
        smtp_encryption: str,
        timeout: int = 30
    ) -> Dict[str, Any]:
        """
        Тест SMTP подключения
        
        Args:
            smtp_server: SMTP сервер
            smtp_port: SMTP порт
            smtp_user: SMTP пользователь
            smtp_password: SMTP пароль
            smtp_encryption: Шифрование
            timeout: Таймаут
            
        Returns:
            Dict: Результат теста
        """
        try:
            smtp = self._get_smtp_connection(
                server=smtp_server,
                port=smtp_port,
                user=smtp_user,
                password=smtp_password,
                encryption=smtp_encryption,
                timeout=timeout
            )
            
            smtp.quit()
            
            return {
                'success': True,
                'message': 'SMTP connection successful',
                'details': {
                    'server': smtp_server,
                    'port': smtp_port,
                    'user': smtp_user,
                    'encryption': smtp_encryption
                }
            }
            
        except Exception as e:
            logger.error(f"SMTP test failed: {e}")
            return {
                'success': False,
                'message': f'SMTP connection failed: {str(e)}',
                'details': {'error': str(e)}
            }
