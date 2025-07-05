import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# My Sport Manager API Configuration
MSM_API_BASE_URL = "https://podrota-ciarandoy.eu1.pitunnel.net"
MSM_CREDENTIALS = {
    "admin": {"username": "test008", "password": "test008"},
    "coach": {"username": "test007", "password": "test007"},
    "user": {"username": "test005", "password": "test005"}
}

# Social Media API Configuration
REDDIT_CONFIG = {
    "client_id": "cYd50HCXatS_OG9kWnBxCg",
    "client_secret": "TsH2BzcHB1ed6-UPnS88sPJbiK5yqw",
    "user_agent": "MySportManagerBot/1.0",
    "username": "mysportmanager",
    "password": "Cn18012009!"
}

INSTAGRAM_CONFIG = {
    "username": "mysportmanager",
    "password": "Cn18012009!",
    "app_id": "1874657053322387",
    "app_secret": "51b1f4bac1af38f7827b97a69f5447e1",
    "token": "",  # Add your Page Access Token here
    "account_id": ""  # Add your Instagram Business Account ID here
}

TIKTOK_CONFIG = {
    "username": "mysportmanager",
    "password": "Cn18012009!"
}

# Content Generation Settings
CONTENT_SETTINGS = {
    "max_posts_per_day": 1,
    "min_time_between_posts": 0,  # hours
    "content_types": ["text", "image", "video"],
    "platforms": ["reddit", "instagram", "tiktok"]
}

# File paths
MEDIA_DIR = "media"
TEMP_DIR = "temp"
LOG_DIR = "logs"

# Create necessary directories
for directory in [MEDIA_DIR, TEMP_DIR, LOG_DIR]:
    os.makedirs(directory, exist_ok=True) 