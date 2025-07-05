import logging
from instagram_poster import InstagramPoster
from create_default_image import create_default_image

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def test_instagram_posting():
    try:
        # First, create the default image if it doesn't exist
        create_default_image()
        
        # Initialize the Instagram poster
        instagram = InstagramPoster()
        
        # Test getting account info
        logger.info("Testing account info retrieval...")
        account_info = instagram.get_account_info()
        logger.info(f"Account info retrieved successfully: {account_info}")
        
        # Test posting text
        logger.info("Testing text post...")
        text_post = instagram.post_text("Testing My Sport Manager Instagram integration! 🎮⚽\n\nThis is a test post to verify our automated posting system is working correctly.")
        logger.info(f"Text post successful: {text_post}")
        
        logger.info("All tests completed successfully!")
        
    except Exception as e:
        logger.error(f"Error during testing: {str(e)}")
        raise

if __name__ == "__main__":
    test_instagram_posting() 