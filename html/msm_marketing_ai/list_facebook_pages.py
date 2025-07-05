import requests
import logging
from config import INSTAGRAM_CONFIG

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def list_facebook_pages():
    access_token = INSTAGRAM_CONFIG['token']
    base_url = "https://graph.facebook.com/v18.0"
    
    try:
        # Get all pages
        url = f"{base_url}/me/accounts"
        params = {
            "access_token": access_token,
            "fields": "id,name,access_token,instagram_business_account"
        }
        
        response = requests.get(url, params=params)
        response.raise_for_status()
        pages = response.json()['data']
        
        if not pages:
            logger.info("No Facebook pages found.")
            return
            
        logger.info("\nFound Facebook Pages:")
        logger.info("-" * 50)
        
        for page in pages:
            logger.info(f"\nPage Name: {page['name']}")
            logger.info(f"Page ID: {page['id']}")
            logger.info(f"Page Access Token: {page['access_token']}")
            
            if 'instagram_business_account' in page:
                instagram_id = page['instagram_business_account']['id']
                logger.info(f"Instagram Business Account ID: {instagram_id}")
                
                # Get Instagram account details
                instagram_url = f"{base_url}/{instagram_id}"
                instagram_params = {
                    "fields": "username,profile_picture_url",
                    "access_token": access_token
                }
                
                instagram_response = requests.get(instagram_url, params=instagram_params)
                instagram_response.raise_for_status()
                instagram_data = instagram_response.json()
                
                logger.info(f"Instagram Username: {instagram_data.get('username', 'N/A')}")
                logger.info(f"Instagram Profile Picture: {instagram_data.get('profile_picture_url', 'N/A')}")
            else:
                logger.info("No Instagram Business Account connected")
            
            logger.info("-" * 50)
            
    except requests.exceptions.RequestException as e:
        logger.error(f"Error: {str(e)}")
        if hasattr(e.response, 'json'):
            logger.error(f"API Response: {e.response.json()}")

if __name__ == "__main__":
    list_facebook_pages() 