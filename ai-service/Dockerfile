FROM python:3.12-slim

WORKDIR /app

# Primero las dependencias: Docker cachea esta capa si no cambian
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 5000

# Gunicorn: servidor WSGI de producción (el servidor de Flask es solo para desarrollo)
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "2", "--timeout", "30", "app:app"]
