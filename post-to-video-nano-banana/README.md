# Post-to-Video (Nano Banana)

Generate short videos from WordPress posts using the Nano Banana API.

## Installation

1. Upload the `post-to-video-nano-banana` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Settings → Post-to-Video** and configure your Nano Banana credentials.

## Configuration

Settings include:

- API base URL and API key.
- Default style preset, voice/language, duration, aspect ratio, captions, and watermark.
- Webhook secret for authenticating callbacks.
- Save locally toggle (store video in Media Library or keep hosted URL).
- Auto-generate on publish toggle.
- Maximum inline images and hourly job limit.

## Generate a video

1. Open a post (or page/custom post type).
2. Use the **Post-to-Video** meta box to generate or regenerate a video.
3. Track status and review log entries.
4. Use the `[nb_video]` shortcode to embed the generated video in content.

## Troubleshooting

- **Connection failed**: Check API base URL and key.
- **Job stuck in processing**: Ensure WP-Cron is running or set up a webhook callback.
- **Rate limit exceeded**: Increase the hourly limit in settings or wait for the next window.
- **Video not saved locally**: Confirm the “Save locally” setting and that your server allows downloads.

## Webhook

Endpoint: `POST /wp-json/post-to-video/v1/webhook`

Provide `x-nb-signature` header with HMAC SHA256 of the JSON payload using your webhook secret.
Include `post_id`, `status`, and `video_url` in the payload.
