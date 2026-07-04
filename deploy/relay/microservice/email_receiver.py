"""
Universal Email Service - Email Receiver
IMAP operations without any database or configuration
"""

import imaplib
import email
from email.header import decode_header
from datetime import datetime
from typing import Optional, List, Dict, Any
import logging
import re
import base64

from models import ReceiveEmailsRequest, EmailMessage

logger = logging.getLogger(__name__)

class EmailReceiver:
    """Handles receiving emails via IMAP - stateless"""
    
    def __init__(self):
        logger.info("EmailReceiver initialized (stateless)")
    
    def _get_imap_connection(
        self,
        server: str,
        port: int,
        user: str,
        password: str,
        encryption: str,
        timeout: int
    ):
        """
        Создать IMAP подключение
        
        Args:
            server: IMAP сервер
            port: IMAP порт
            user: IMAP пользователь
            password: IMAP пароль
            encryption: Тип шифрования (ssl, tls, none)
            timeout: Таймаут
            
        Returns:
            imaplib.IMAP4: IMAP подключение
        """
        try:
            encryption = encryption.lower()
            
            if encryption == 'ssl':
                imap = imaplib.IMAP4_SSL(server, port)
            else:
                imap = imaplib.IMAP4(server, port)
                if encryption == 'tls':
                    imap.starttls()
            
            # Авторизация
            imap.login(user, password)
            
            logger.info(f"IMAP connected: {server}:{port} (encryption={encryption})")
            return imap
            
        except Exception as e:
            logger.error(f"Failed to connect to IMAP: {e}")
            raise
    
    def _decode_header(self, header_value: str) -> str:
        """Декодировать заголовок письма"""
        if not header_value:
            return ""
        
        decoded_parts = decode_header(header_value)
        result = []
        
        for part, encoding in decoded_parts:
            if isinstance(part, bytes):
                try:
                    if encoding:
                        result.append(part.decode(encoding))
                    else:
                        result.append(part.decode('utf-8', errors='ignore'))
                except:
                    result.append(part.decode('utf-8', errors='ignore'))
            else:
                result.append(str(part))
        
        return ' '.join(result)
    
    def _extract_email_address(self, email_str: str) -> str:
        """Извлечь email из строки 'Name <email@example.com>'"""
        match = re.search(r'<(.+?)>', email_str)
        if match:
            return match.group(1)
        return email_str.strip()
    
    def _extract_name(self, email_str: str) -> Optional[str]:
        """Извлечь имя из строки 'Name <email@example.com>'"""
        match = re.search(r'^(.+?)\s*<', email_str)
        if match:
            return match.group(1).strip('"').strip()
        return None
    
    def _get_email_body(self, msg: email.message.Message) -> tuple[Optional[str], Optional[str]]:
        """
        Извлечь тело письма
        
        Returns:
            tuple: (text_body, html_body)
        """
        text_body = None
        html_body = None
        
        if msg.is_multipart():
            for part in msg.walk():
                content_type = part.get_content_type()
                content_disposition = str(part.get("Content-Disposition", ""))
                
                if "attachment" in content_disposition:
                    continue
                
                try:
                    body = part.get_payload(decode=True)
                    if body:
                        charset = part.get_content_charset() or 'utf-8'
                        body = body.decode(charset, errors='ignore')
                        
                        if content_type == "text/plain":
                            text_body = body
                        elif content_type == "text/html":
                            html_body = body
                except Exception as e:
                    logger.warning(f"Failed to decode part: {e}")
        else:
            try:
                body = msg.get_payload(decode=True)
                if body:
                    charset = msg.get_content_charset() or 'utf-8'
                    body = body.decode(charset, errors='ignore')
                    
                    if msg.get_content_type() == "text/html":
                        html_body = body
                    else:
                        text_body = body
            except Exception as e:
                logger.warning(f"Failed to decode body: {e}")
        
        return text_body, html_body
    
    def _get_attachments(self, msg: email.message.Message) -> List[Dict[str, Any]]:
        """Извлечь информацию о вложениях"""
        attachments = []
        
        if msg.is_multipart():
            for part in msg.walk():
                if part.get_content_disposition() == 'attachment':
                    filename = part.get_filename()
                    if filename:
                        filename = self._decode_header(filename)
                        
                        # Извлекаем данные вложения
                        attachment_data = part.get_payload(decode=True)
                        
                        # Кодируем в base64
                        attachment_base64 = base64.b64encode(attachment_data).decode('utf-8')
                        
                        attachments.append({
                            'filename': filename,
                            'content_type': part.get_content_type(),
                            'size': len(attachment_data),
                            'data': attachment_base64
                        })
        
        return attachments
    
    def _parse_email(self, email_data: bytes) -> Optional[EmailMessage]:
        """
        Распарсить email сообщение
        
        Args:
            email_data: Сырые данные письма
            
        Returns:
            EmailMessage: Распарсенное письмо
        """
        try:
            msg = email.message_from_bytes(email_data)
            
            # Заголовки
            from_header = self._decode_header(msg.get('From', ''))
            from_email = self._extract_email_address(from_header)
            from_name = self._extract_name(from_header)
            
            to_header = self._decode_header(msg.get('To', ''))
            to_email = self._extract_email_address(to_header)
            to_name = self._extract_name(to_header)
            
            subject = self._decode_header(msg.get('Subject', ''))
            message_id = msg.get('Message-ID', '')
            in_reply_to = msg.get('In-Reply-To', '')
            references = msg.get('References', '')
            
            # Дата
            date_str = msg.get('Date', '')
            try:
                date = email.utils.parsedate_to_datetime(date_str)
            except:
                date = None
            
            # Тело
            text_body, html_body = self._get_email_body(msg)
            
            # Вложения
            attachments = self._get_attachments(msg)
            
            # Дополнительные заголовки
            headers = {}
            for key, value in msg.items():
                headers[key] = self._decode_header(value)
            
            return EmailMessage(
                from_email=from_email,
                from_name=from_name,
                to_email=to_email,
                to_name=to_name,
                subject=subject,
                body_text=text_body,
                body_html=html_body,
                message_id=message_id,
                in_reply_to=in_reply_to,
                references=references,
                date=date,
                has_attachments=len(attachments) > 0,
                attachments_count=len(attachments),
                attachments=attachments if attachments else None,
                is_seen=False,
                headers=headers
            )
            
        except Exception as e:
            logger.error(f"Failed to parse email: {e}", exc_info=True)
            return None
    
    def _build_search_criteria(
        self,
        unseen_only: bool,
        since_date: Optional[datetime],
        before_date: Optional[datetime]
    ) -> str:
        """
        Построить критерии поиска для IMAP
        
        Returns:
            str: Критерии поиска
        """
        criteria = []
        
        if unseen_only:
            criteria.append('UNSEEN')
        else:
            criteria.append('ALL')
        
        if since_date:
            date_str = since_date.strftime('%d-%b-%Y')
            criteria.append(f'SINCE {date_str}')
        
        if before_date:
            date_str = before_date.strftime('%d-%b-%Y')
            criteria.append(f'BEFORE {date_str}')
        
        return ' '.join(criteria)
    
    async def receive_emails(self, request: ReceiveEmailsRequest) -> List[EmailMessage]:
        """
        Получить emails
        
        Args:
            request: Параметры получения
            
        Returns:
            List[EmailMessage]: Список писем
        """
        emails = []
        imap = None
        
        try:
            # Подключение
            imap = self._get_imap_connection(
                server=request.imap_server,
                port=request.imap_port,
                user=request.imap_user,
                password=request.imap_password,
                encryption=request.imap_encryption,
                timeout=request.timeout
            )
            
            # Выбор папки
            imap.select(request.folder)
            
            # Критерии поиска
            search_criteria = self._build_search_criteria(
                unseen_only=request.unseen_only,
                since_date=request.since_date,
                before_date=request.before_date
            )
            
            # Поиск
            status, messages = imap.search(None, search_criteria)
            
            if status != 'OK':
                logger.warning(f"IMAP search failed: {status}")
                return emails
            
            email_ids = messages[0].split()
            
            # Ограничение
            email_ids = email_ids[-request.limit:] if len(email_ids) > request.limit else email_ids
            
            logger.info(f"Found {len(email_ids)} emails matching criteria")
            
            # Обработка каждого письма
            for email_id in email_ids:
                try:
                    # Получаем письмо
                    status, msg_data = imap.fetch(email_id, '(RFC822 FLAGS)')
                    
                    if status != 'OK':
                        continue
                    
                    # Парсим
                    email_data = msg_data[0][1]
                    parsed_email = self._parse_email(email_data)
                    
                    if not parsed_email:
                        continue
                    
                    # Проверяем флаг SEEN
                    flags = msg_data[0][0].decode('utf-8')
                    parsed_email.is_seen = '\\Seen' in flags
                    
                    emails.append(parsed_email)
                    
                    # КРИТИЧНО: Помечаем как прочитанное ТОЛЬКО если параметр mark_as_read = True
                    if request.mark_as_read and not parsed_email.is_seen:
                        imap.store(email_id, '+FLAGS', '\\Seen')
                        logger.info(f"Marked email {email_id} as read")
                    
                except Exception as e:
                    logger.error(f"Failed to process email {email_id}: {e}")
                    continue
            
            logger.info(f"Successfully parsed {len(emails)} emails")
            return emails
            
        except Exception as e:
            logger.error(f"Failed to receive emails: {e}", exc_info=True)
            raise
            
        finally:
            if imap:
                try:
                    imap.close()
                    imap.logout()
                except:
                    pass
    
    async def test_connection(
        self,
        imap_server: str,
        imap_port: int,
        imap_user: str,
        imap_password: str,
        imap_encryption: str,
        timeout: int = 30
    ) -> Dict[str, Any]:
        """
        Тест IMAP подключения
        
        Returns:
            Dict: Результат теста
        """
        try:
            imap = self._get_imap_connection(
                server=imap_server,
                port=imap_port,
                user=imap_user,
                password=imap_password,
                encryption=imap_encryption,
                timeout=timeout
            )
            
            # Проверяем доступ к INBOX
            status, folders = imap.list()
            imap.select('INBOX')
            status, messages = imap.search(None, 'ALL')
            message_count = len(messages[0].split()) if messages[0] else 0
            
            imap.close()
            imap.logout()
            
            return {
                'success': True,
                'message': 'IMAP connection successful',
                'details': {
                    'server': imap_server,
                    'port': imap_port,
                    'user': imap_user,
                    'encryption': imap_encryption,
                    'inbox_message_count': message_count
                }
            }
            
        except Exception as e:
            logger.error(f"IMAP test failed: {e}")
            return {
                'success': False,
                'message': f'IMAP connection failed: {str(e)}',
                'details': {'error': str(e)}
            }
