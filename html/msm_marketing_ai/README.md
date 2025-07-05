# My Sport Manager Social Media Automation

This system automatically generates and posts content about My Sport Manager to various social media platforms (Reddit, Instagram, and TikTok) using AI-powered content generation.

## Features

- AI-powered content generation for text, images, and videos
- Automated posting to multiple social media platforms
- Content scheduling and rate limiting
- Integration with My Sport Manager platform data
- Logging and error handling

## Prerequisites

- Python 3.8 or higher
- OpenAI API key
- Social media platform API credentials:
  - Reddit API credentials
  - Instagram account credentials
  - TikTok API credentials (if available)

## Installation

1. Clone this repository:
```bash
git clone <repository-url>
cd msm_marketing_ai
```

2. Install required packages:
```bash
pip install -r requirements.txt
pip install --user instabot==0.117.0
```

3. Create a `.env` file in the project root with the following variables:
```
OPENAI_API_KEY=your_openai_api_key
REDDIT_CLIENT_ID=your_reddit_client_id
REDDIT_CLIENT_SECRET=your_reddit_client_secret
INSTAGRAM_USERNAME=your_instagram_username
INSTAGRAM_PASSWORD=your_instagram_password
TIKTOK_USERNAME=your_tiktok_username
TIKTOK_PASSWORD=your_tiktok_password
```

## Usage

1. Configure the settings in `config.py` if needed:
   - Adjust posting frequency
   - Modify content types
   - Change target platforms

2. Run the automation system:
```bash
python main.py
```

The system will:
- Generate content using AI
- Post to social media platforms
- Maintain posting schedules
- Log all activities

## Directory Structure

- `main.py`: Main script to run the automation
- `content_generator.py`: AI content generation
- `social_media_poster.py`: Social media posting handlers
- `config.py`: Configuration settings
- `media/`: Generated media files
- `logs/`: Activity logs
- `temp/`: Temporary files

## Notes

- The system uses the My Sport Manager API at podrota-ciarandoy.eu1.pitunnel.net
- Default credentials are configured for testing
- TikTok posting may be limited due to API restrictions
- Content generation uses OpenAI's GPT-4 and DALL-E models

## Troubleshooting

1. Check the logs in the `logs/` directory for error messages
2. Verify API credentials in the `.env` file
3. Ensure all required packages are installed
4. Check internet connectivity
5. Verify My Sport Manager API accessibility

## Contributing

Feel free to submit issues and enhancement requests! 