# Usa un'immagine PHP leggera
FROM php:8.2-cli-alpine

# Installa curl per le richieste HTTP
RUN apk --no-cache add curl

# Imposta la working directory
WORKDIR /app

# Copia lo script PHP nel container
COPY webhookListener.php .

# Espone la porta per i webhook
EXPOSE 8080

# Definisce variabili d’ambiente (modificabili con -e)
ENV VIKUNJA_URL="http://vikunja/api/v1/"
ENV VIKUNJA_API_TOKEN="your_api_token_here"

# Avvia il server PHP
CMD ["php", "-S", "0.0.0.0:8080", "webhookListener.php"]