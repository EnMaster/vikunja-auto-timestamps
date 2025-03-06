# Vikunja Auto Timestamps

### Description
This is a lightweight PHP application that listens for Vikunja webhook events and automatically updates task timestamps based on their movement between buckets. Specifically, it records the _start time_ when a task moves from "backlog" to "in progress" and the _end time_ when it moves to "done."

### Features
- Listens for `task.updated` events from Vikunja.
- Detects when a task moves between buckets.
- Sets `start_date` when a task leaves the backlog.
- Sets `end_date` when a task is moved to done.
- Uses PHP's built-in web server (`php -S`) for quick deployment.

### Requirements
- PHP 8.0 or later
- cURL extension enabled
- A running Vikunja instance with webhook support
- 3 buckets:
    - Backlog
    - A middle one bucket
    - Done

### Installation
1. Clone this repository:
   ```sh
   git clone https://github.com/EnMaster/vikunja-auto-timestamps.git
   cd vikunja-auto-timestamps
   ```
2. Copy the `.env.example` file and configure your Vikunja API credentials:
   ```sh
   cp .env.example .env
   ```
3. Start the PHP built-in server or copy in your web server:
   ```sh
   php -S 0.0.0.0:8080
   ```
4. Configure your Vikunja webhook to point to `http://yourserver:8080`.

### Environment Variables
The application need environment variables to configure API access. These should be defined in a `.env` file:
```ini
VIKUNJA_URL=https://your-vikunja-instance/api/v1/
VIKUNJA_TOKEN=your_api_token
```

## Deployment with Docker
You can run the script in a lightweight Docker container:
```sh
docker build -t vikunja-webhook .
docker run -p 8080:8080 --env-file .env vikunja-webhook
```



## Notes
- Ensure that your Vikunja instance is configured to send webhooks. You only need the `task.updater`
- This script does not store data locally; all updates are made via the Vikunja API, so you need create an API Key from Vikunja GUI.

## Roadmap
- Add control to track which bucket a task is moving from and to. 
- Allow customization of the start and end bucket definitions.
- Improve logging and error handling.


