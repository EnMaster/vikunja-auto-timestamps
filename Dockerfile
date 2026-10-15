# Use a lightweight PHP image
FROM php:8.2-cli-alpine

# Set the working directory
WORKDIR /app

# Copy the PHP script into the container
COPY webhookListener.php .

# Run as a non-root user
RUN adduser -D -u 1000 appuser
USER appuser

# Expose the webhook port
EXPOSE 8080

# Define environment variables (overridable with -e or --env-file)
ENV VIKUNJA_URL="http://vikunja/api/v1/"
ENV VIKUNJA_API_TOKEN="your_api_token_here"
ENV VIKUNJA_WEBHOOK_SECRET="your_webhook_secret_here"

# Start the PHP server
CMD ["php", "-S", "0.0.0.0:8080", "webhookListener.php"]
