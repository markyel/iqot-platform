"""
Universal Email Service - PDF Generator
HTML to PDF conversion using WeasyPrint
"""

import base64
import logging
from typing import Optional
from weasyprint import HTML, CSS

try:
    from weasyprint.text.fonts import FontConfiguration
    FONT_CONFIG_AVAILABLE = True
except ImportError:
    try:
        from weasyprint.fonts import FontConfiguration
        FONT_CONFIG_AVAILABLE = True
    except ImportError:
        FONT_CONFIG_AVAILABLE = False

logger = logging.getLogger(__name__)


class PDFGenerator:
    """Handles HTML to PDF conversion using WeasyPrint"""
    
    def __init__(self):
        if FONT_CONFIG_AVAILABLE:
            self.font_config = FontConfiguration()
        else:
            self.font_config = None
        logger.info(f"PDFGenerator initialized (font_config: {FONT_CONFIG_AVAILABLE})")
    
    def html_to_pdf(
        self,
        html_content: str,
        css_content: Optional[str] = None,
        base_url: Optional[str] = None,
        page_size: str = "A4",
        landscape: bool = False,
        margin_top: str = "10mm",
        margin_bottom: str = "10mm",
        margin_left: str = "10mm",
        margin_right: str = "10mm"
    ) -> bytes:
        try:
            orientation = "landscape" if landscape else "portrait"
            
            page_css = f"""
            @page {{
                size: {page_size} {orientation};
                margin-top: {margin_top};
                margin-bottom: {margin_bottom};
                margin-left: {margin_left};
                margin-right: {margin_right};
            }}
            table {{ width: 100% !important; border-collapse: collapse; }}
            tr {{ page-break-inside: avoid; }}
            thead {{ display: table-header-group; }}
            body {{ font-family: Arial, sans-serif; font-size: 10pt; }}
            """
            
            full_css = page_css
            if css_content:
                full_css += "\n" + css_content
            
            html = HTML(string=html_content, base_url=base_url)
            css = CSS(string=full_css)
            
            # Совместимость с разными версиями WeasyPrint
            try:
                if self.font_config:
                    pdf_bytes = html.write_pdf(stylesheets=[css], font_config=self.font_config)
                else:
                    pdf_bytes = html.write_pdf(stylesheets=[css])
            except TypeError:
                pdf_bytes = html.write_pdf(stylesheets=[css])
            
            logger.info(f"PDF generated: {len(pdf_bytes)} bytes")
            return pdf_bytes
            
        except Exception as e:
            logger.error(f"Failed to generate PDF: {e}", exc_info=True)
            raise
    
    def html_to_pdf_base64(self, **kwargs) -> str:
        pdf_bytes = self.html_to_pdf(**kwargs)
        return base64.b64encode(pdf_bytes).decode('utf-8')
