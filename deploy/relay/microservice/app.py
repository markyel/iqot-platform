"""
Universal Email Service
Simple stateless microservice for sending and receiving emails
+ HTML to PDF conversion
No database, no configuration - just pure operations
"""

from fastapi import FastAPI, HTTPException, Depends, Header
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response
from typing import Optional
import uvicorn
import logging
import base64
import os
import hmac

from models import (
    SendEmailRequest,
    SendEmailResponse,
    ReceiveEmailsRequest,
    ReceiveEmailsResponse,
    TestConnectionRequest,
    TestConnectionResponse,
    HtmlToPdfRequest,
    HtmlToPdfResponse
)
from email_sender import EmailSender
from email_receiver import EmailReceiver
from pdf_generator import PDFGenerator

# Настройка логирования
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# ==================== АВТОРИЗАЦИЯ ====================
# Открытый sendmail-эндпоинт недопустим. Все операционные эндпоинты (/send, /receive,
# /test-*, /html-to-pdf*) требуют заголовок X-API-Key, совпадающий с секретом из env
# API_KEY. Fail-closed: если ключ на сервере не задан — эндпоинты возвращают 503
# (сервис не поднимается в незащищённом режиме). / и /health открыты (healthcheck).
API_KEY = os.environ.get("API_KEY", "").strip()


async def require_api_key(x_api_key: Optional[str] = Header(None)):
    if not API_KEY:
        raise HTTPException(status_code=503, detail="API key not configured on server")
    if not x_api_key or not hmac.compare_digest(x_api_key, API_KEY):
        raise HTTPException(status_code=401, detail="Invalid or missing X-API-Key")


# Создание FastAPI приложения
app = FastAPI(
    title="Universal Email Service",
    description="Stateless microservice for sending/receiving emails via SMTP/IMAP + HTML to PDF conversion",
    version="2.1.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Инициализация сервисов
email_sender = EmailSender()
email_receiver = EmailReceiver()
pdf_generator = PDFGenerator()

# ==================== ENDPOINTS ====================

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "service": "Universal Email Service",
        "version": "2.1.0",
        "status": "running",
        "description": "Stateless email service + PDF generation",
        "endpoints": {
            "email": ["/send", "/receive", "/test-smtp", "/test-imap"],
            "pdf": ["/html-to-pdf", "/html-to-pdf-base64"]
        }
    }

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "universal-email-service",
        "features": ["email", "pdf"]
    }

# ==================== EMAIL ENDPOINTS ====================

@app.post("/send", response_model=SendEmailResponse, dependencies=[Depends(require_api_key)])
async def send_email(request: SendEmailRequest):
    """
    Отправить email через SMTP
    
    Все параметры передаются в запросе - сервис не хранит credentials
    """
    try:
        logger.info(f"Sending email: from={request.from_email}, to={request.to_email}")
        
        result = await email_sender.send_email(request)
        
        logger.info(f"Email sent successfully: message_id={result.get('message_id')}")
        
        return SendEmailResponse(
            success=True,
            message="Email sent successfully",
            message_id=result.get('message_id'),
            details=result
        )
        
    except ValueError as e:
        logger.error(f"Validation error: {e}")
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.error(f"Failed to send email: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Failed to send email: {str(e)}")

@app.post("/receive", response_model=ReceiveEmailsResponse, dependencies=[Depends(require_api_key)])
async def receive_emails(request: ReceiveEmailsRequest):
    """
    Получить emails через IMAP
    
    Все параметры передаются в запросе - сервис не хранит credentials
    """
    try:
        logger.info(f"Receiving emails: server={request.imap_server}, user={request.imap_user}")
        
        emails = await email_receiver.receive_emails(request)
        
        logger.info(f"Retrieved {len(emails)} emails")
        
        return ReceiveEmailsResponse(
            success=True,
            emails_count=len(emails),
            emails=emails
        )
        
    except ValueError as e:
        logger.error(f"Validation error: {e}")
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.error(f"Failed to receive emails: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Failed to receive emails: {str(e)}")

@app.post("/test-smtp", response_model=TestConnectionResponse, dependencies=[Depends(require_api_key)])
async def test_smtp(request: TestConnectionRequest):
    """Протестировать SMTP подключение"""
    try:
        logger.info(f"Testing SMTP: {request.smtp_server}:{request.smtp_port}")
        
        result = await email_sender.test_connection(
            smtp_server=request.smtp_server,
            smtp_port=request.smtp_port,
            smtp_user=request.smtp_user,
            smtp_password=request.smtp_password,
            smtp_encryption=request.smtp_encryption
        )
        
        return TestConnectionResponse(
            success=result['success'],
            message=result['message'],
            details=result.get('details')
        )
        
    except Exception as e:
        logger.error(f"SMTP test failed: {e}")
        return TestConnectionResponse(
            success=False,
            message=f"SMTP test failed: {str(e)}",
            details={'error': str(e)}
        )

@app.post("/test-imap", response_model=TestConnectionResponse, dependencies=[Depends(require_api_key)])
async def test_imap(request: TestConnectionRequest):
    """Протестировать IMAP подключение"""
    try:
        logger.info(f"Testing IMAP: {request.imap_server}:{request.imap_port}")
        
        result = await email_receiver.test_connection(
            imap_server=request.imap_server,
            imap_port=request.imap_port,
            imap_user=request.imap_user,
            imap_password=request.imap_password,
            imap_encryption=request.imap_encryption
        )
        
        return TestConnectionResponse(
            success=result['success'],
            message=result['message'],
            details=result.get('details')
        )
        
    except Exception as e:
        logger.error(f"IMAP test failed: {e}")
        return TestConnectionResponse(
            success=False,
            message=f"IMAP test failed: {str(e)}",
            details={'error': str(e)}
        )

# ==================== PDF ENDPOINTS ====================

@app.post("/html-to-pdf", dependencies=[Depends(require_api_key)])
async def html_to_pdf(request: HtmlToPdfRequest):
    """
    Конвертировать HTML в PDF
    
    Возвращает:
    - Бинарный PDF файл (по умолчанию)
    - JSON с base64 (если return_base64=true)
    
    Особенности:
    - Таблицы растягиваются на всю ширину страницы
    - Поддержка кириллицы
    - Настраиваемые отступы и ориентация
    """
    try:
        logger.info(f"Converting HTML to PDF: landscape={request.landscape}, page_size={request.page_size}")
        
        pdf_bytes = pdf_generator.html_to_pdf(
            html_content=request.html_content,
            css_content=request.css_content,
            base_url=request.base_url,
            page_size=request.page_size,
            landscape=request.landscape,
            margin_top=request.margin_top,
            margin_bottom=request.margin_bottom,
            margin_left=request.margin_left,
            margin_right=request.margin_right
        )
        
        logger.info(f"PDF generated: {len(pdf_bytes)} bytes")
        
        if request.return_base64:
            # Возвращаем JSON с base64
            return HtmlToPdfResponse(
                success=True,
                filename=request.filename,
                content_base64=base64.b64encode(pdf_bytes).decode('utf-8'),
                size_bytes=len(pdf_bytes)
            )
        else:
            # Возвращаем бинарный PDF
            return Response(
                content=pdf_bytes,
                media_type="application/pdf",
                headers={
                    "Content-Disposition": f'attachment; filename="{request.filename}"'
                }
            )
        
    except Exception as e:
        logger.error(f"Failed to convert HTML to PDF: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Failed to generate PDF: {str(e)}")


@app.post("/html-to-pdf-base64", response_model=HtmlToPdfResponse, dependencies=[Depends(require_api_key)])
async def html_to_pdf_base64(request: HtmlToPdfRequest):
    """
    Конвертировать HTML в PDF и вернуть base64
    
    Удобно для n8n - возвращает JSON с base64-encoded PDF
    """
    request.return_base64 = True
    return await html_to_pdf(request)


# ==================== MAIN ====================

if __name__ == "__main__":
    uvicorn.run(
        "app:app",
        host="0.0.0.0",
        port=8000,
        reload=False,
        log_level="info"
    )
