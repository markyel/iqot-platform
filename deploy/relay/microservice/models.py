"""
Universal Email Service - Models
Pydantic models for API requests and responses
"""

from pydantic import BaseModel, EmailStr, Field
from typing import Optional, List, Dict, Any
from datetime import datetime

# ==================== COMMON MODELS ====================

class EmailAttachment(BaseModel):
    """Email вложение"""
    filename: str
    content: str  # Base64 encoded
    content_type: Optional[str] = "application/octet-stream"

# ==================== SEND EMAIL ====================

class SendEmailRequest(BaseModel):
    """Запрос на отправку email - все параметры в запросе"""
    
    # SMTP настройки
    smtp_server: str = Field(..., description="SMTP сервер (smtp.yandex.ru)")
    smtp_port: int = Field(..., description="SMTP порт (465 для SSL, 587 для TLS)")
    smtp_user: str = Field(..., description="SMTP логин")
    smtp_password: str = Field(..., description="SMTP пароль")
    smtp_encryption: str = Field(default="ssl", description="Тип шифрования: ssl, tls, none")
    
    # Email данные
    from_email: EmailStr = Field(..., description="Email отправителя")
    from_name: Optional[str] = Field(None, description="Имя отправителя")
    to_email: EmailStr = Field(..., description="Email получателя")
    subject: str = Field(..., description="Тема письма")
    body_html: str = Field(..., description="HTML тело письма")
    body_text: Optional[str] = Field(None, description="Текстовое тело письма")
    
    # Опциональное
    attachments: Optional[List[EmailAttachment]] = Field(None, description="Вложения")
    reply_to: Optional[str] = Field(None, description="Reply-To адрес")
    cc: Optional[List[str]] = Field(None, description="Копия (CC)")
    bcc: Optional[List[str]] = Field(None, description="Скрытая копия (BCC)")

    # Кастомные заголовки — свой Message-ID (для записи в email_messages на стороне
    # Laravel) и threading ответов (In-Reply-To / References, чтобы ответ лёг в цепочку
    # у поставщика). Передаются уже с угловыми скобками, ставятся как есть.
    message_id: Optional[str] = Field(None, description="Свой Message-ID (в угловых скобках)")
    in_reply_to: Optional[str] = Field(None, description="In-Reply-To заголовок")
    references: Optional[str] = Field(None, description="References заголовок")

    # Проверка TLS-сертификата (ssl/tls). False → не проверять hostname (провайдеры с
    # общим сертификатом, напр. sprinthost CN=from.sh при коннекте на smtp.wwwsend.ru).
    # CA-валидность цепочки сохраняется (verify_mode=CERT_REQUIRED). Beget — True.
    verify_cert: bool = Field(default=True, description="Проверять hostname TLS-сертификата")

    # Настройки отправки
    timeout: int = Field(default=30, description="Таймаут подключения в секундах")
    retry_attempts: int = Field(default=3, description="Количество попыток при ошибке")
    retry_delay: int = Field(default=5, description="Задержка между попытками в секундах")
    
    class Config:
        json_schema_extra = {
            "example": {
                "smtp_server": "smtp.yandex.ru",
                "smtp_port": 465,
                "smtp_user": "user@yandex.ru",
                "smtp_password": "password123",
                "smtp_encryption": "ssl",
                "from_email": "user@yandex.ru",
                "from_name": "Петров А.И.",
                "to_email": "supplier@example.com",
                "subject": "Запрос коммерческого предложения",
                "body_html": "<html><body><h1>Добрый день!</h1></body></html>",
                "body_text": "Добрый день!",
                "timeout": 30,
                "retry_attempts": 3,
                "retry_delay": 5
            }
        }

class SendEmailResponse(BaseModel):
    """Ответ на отправку email"""
    success: bool
    message: str
    message_id: Optional[str] = None
    details: Optional[Dict[str, Any]] = None

# ==================== RECEIVE EMAILS ====================

class ReceiveEmailsRequest(BaseModel):
    """Запрос на получение emails - все параметры в запросе"""
    
    # IMAP настройки
    imap_server: str = Field(..., description="IMAP сервер (imap.yandex.ru)")
    imap_port: int = Field(..., description="IMAP порт (обычно 993)")
    imap_user: str = Field(..., description="IMAP логин")
    imap_password: str = Field(..., description="IMAP пароль")
    imap_encryption: str = Field(default="ssl", description="Тип шифрования: ssl, tls, none")
    
    # Параметры получения
    folder: str = Field(default="INBOX", description="Папка для чтения")
    limit: int = Field(default=50, description="Максимум писем")
    unseen_only: bool = Field(default=True, description="Только непрочитанные")
    mark_as_read: bool = Field(default=False, description="Пометить как прочитанные")
    
    # Фильтры по дате
    since_date: Optional[datetime] = Field(None, description="Письма после этой даты")
    before_date: Optional[datetime] = Field(None, description="Письма до этой даты")
    
    # Настройки подключения
    timeout: int = Field(default=30, description="Таймаут подключения в секундах")
    
    class Config:
        json_schema_extra = {
            "example": {
                "imap_server": "imap.yandex.ru",
                "imap_port": 993,
                "imap_user": "user@yandex.ru",
                "imap_password": "password123",
                "imap_encryption": "ssl",
                "folder": "INBOX",
                "limit": 50,
                "unseen_only": True,
                "mark_as_read": False,
                "since_date": "2025-01-20T00:00:00",
                "timeout": 30
            }
        }

class EmailMessage(BaseModel):
    """Полученное email сообщение"""
    from_email: str
    from_name: Optional[str] = None
    to_email: str
    to_name: Optional[str] = None
    subject: Optional[str] = None
    body_text: Optional[str] = None
    body_html: Optional[str] = None
    
    # Заголовки
    message_id: Optional[str] = None
    in_reply_to: Optional[str] = None
    references: Optional[str] = None
    
    # Метаданные
    date: Optional[datetime] = None
    has_attachments: bool = False
    attachments_count: int = 0
    attachments: Optional[List[Dict[str, Any]]] = None
    
    # Дополнительно
    is_seen: bool = False
    headers: Optional[Dict[str, str]] = None
    
    class Config:
        json_schema_extra = {
            "example": {
                "from_email": "supplier@example.com",
                "from_name": "ООО Поставщик",
                "to_email": "user@yandex.ru",
                "subject": "Re: Запрос КП",
                "body_text": "Добрый день! Направляем КП...",
                "body_html": "<html>...</html>",
                "message_id": "<abc123@example.com>",
                "in_reply_to": "<xyz456@yandex.ru>",
                "date": "2025-01-24T10:30:00",
                "has_attachments": True,
                "attachments_count": 2,
                "is_seen": False
            }
        }

class ReceiveEmailsResponse(BaseModel):
    """Ответ на получение emails"""
    success: bool
    emails_count: int
    emails: List[EmailMessage]

# ==================== TEST CONNECTION ====================

class TestConnectionRequest(BaseModel):
    """Запрос на тест подключения"""
    
    # SMTP (опционально)
    smtp_server: Optional[str] = None
    smtp_port: Optional[int] = None
    smtp_user: Optional[str] = None
    smtp_password: Optional[str] = None
    smtp_encryption: Optional[str] = "ssl"
    
    # IMAP (опционально)
    imap_server: Optional[str] = None
    imap_port: Optional[int] = None
    imap_user: Optional[str] = None
    imap_password: Optional[str] = None
    imap_encryption: Optional[str] = "ssl"
    
    # Настройки
    timeout: int = Field(default=30, description="Таймаут в секундах")

class TestConnectionResponse(BaseModel):
    """Ответ на тест подключения"""
    success: bool
    message: str
    details: Optional[Dict[str, Any]] = None


# ==================== PDF GENERATION ====================

class HtmlToPdfRequest(BaseModel):
    """Запрос на конвертацию HTML в PDF"""
    
    html_content: str = Field(..., description="HTML контент для конвертации")
    css_content: Optional[str] = Field(None, description="Дополнительный CSS")
    base_url: Optional[str] = Field(None, description="Base URL для ресурсов")
    
    # Настройки страницы
    page_size: str = Field(default="A4", description="Размер: A4, Letter, A3, Legal, etc.")
    landscape: bool = Field(default=False, description="Альбомная ориентация")
    margin_top: str = Field(default="10mm", description="Верхний отступ")
    margin_bottom: str = Field(default="10mm", description="Нижний отступ")
    margin_left: str = Field(default="10mm", description="Левый отступ")
    margin_right: str = Field(default="10mm", description="Правый отступ")
    
    # Формат ответа
    return_base64: bool = Field(default=False, description="Вернуть base64 вместо бинарного файла")
    filename: str = Field(default="document.pdf", description="Имя файла")
    
    class Config:
        json_schema_extra = {
            "example": {
                "html_content": """
                    <html>
                    <head><title>Report</title></head>
                    <body>
                        <h1>Отчёт по заявке REQ-123</h1>
                        <table border="1">
                            <tr><th>Товар</th><th>Цена</th><th>Поставщик</th></tr>
                            <tr><td>Плата XYZ</td><td>15 000 ₽</td><td>ООО Поставщик</td></tr>
                        </table>
                    </body>
                    </html>
                """,
                "page_size": "A4",
                "landscape": True,
                "margin_top": "15mm",
                "margin_bottom": "15mm",
                "margin_left": "10mm",
                "margin_right": "10mm",
                "return_base64": True,
                "filename": "report.pdf"
            }
        }


class HtmlToPdfResponse(BaseModel):
    """Ответ с PDF в base64"""
    success: bool
    filename: str
    content_base64: str
    size_bytes: int
    
    class Config:
        json_schema_extra = {
            "example": {
                "success": True,
                "filename": "report.pdf",
                "content_base64": "JVBERi0xLjQKJeLjz9MKMSAwIG9iago8PC...",
                "size_bytes": 45678
            }
        }
