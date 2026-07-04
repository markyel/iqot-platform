FROM python:3.11-slim

# ========================================
# WeasyPrint system dependencies
# ========================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Build tools
    build-essential \
    # WeasyPrint core dependencies
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libpangoft2-1.0-0 \
    libgdk-pixbuf-2.0-0 \
    libffi-dev \
    shared-mime-info \
    # Cairo
    libcairo2 \
    libcairo2-dev \
    # GObject introspection
    libgirepository1.0-dev \
    gir1.2-pango-1.0 \
    # Fonts (важно для кириллицы!)
    fonts-liberation \
    fonts-dejavu \
    fonts-dejavu-core \
    fonts-dejavu-extra \
    fonts-freefont-ttf \
    fonts-noto \
    # Curl for healthcheck
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && fc-cache -f -v

# ========================================
# Python app
# ========================================
WORKDIR /app

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY . .

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Run
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
