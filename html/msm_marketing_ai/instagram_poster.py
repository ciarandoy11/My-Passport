import os
import requests
import logging
from typing import Optional, Dict, Any
from config import INSTAGRAM_CONFIG

class InstagramPoster:
    def __init__(self):
        self.access_token = INSTAGRAM_CONFIG['token']
        self.account_id = INSTAGRAM_CONFIG['account_id']
        self.base_url = "https://graph.facebook.com/v18.0"
        
        if not self.access_token:
            logging.error("Instagram access token not found in config")
            raise ValueError("Instagram access token not found")
            
        if not self.account_id or self.account_id == "YOUR_INSTAGRAM_ACCOUNT_ID":
            logging.error("Instagram account ID not configured")
            raise ValueError("Instagram account ID not configured")

    def post_image(self, image_path: str, caption: str) -> Dict[str, Any]:
        """
        Post an image to Instagram using the Graph API.
        
        Args:
            image_path: Path to the image file
            caption: Caption for the post
            
        Returns:
            Dict containing the API response
        """
        try:
            # Step 1: Create media container
            container_url = f"{self.base_url}/{self.account_id}/media"
            container_data = {
                "image_url": image_path,
                "caption": caption,
                "access_token": self.access_token
            }
            
            container_response = requests.post(container_url, data=container_data)
            container_response.raise_for_status()
            creation_id = container_response.json()['id']
            
            # Step 2: Publish the media
            publish_url = f"{self.base_url}/{self.account_id}/media_publish"
            publish_data = {
                "creation_id": creation_id,
                "access_token": self.access_token
            }
            
            publish_response = requests.post(publish_url, data=publish_data)
            publish_response.raise_for_status()
            
            logging.info(f"Successfully posted image to Instagram: {publish_response.json()}")
            return publish_response.json()
            
        except requests.exceptions.RequestException as e:
            logging.error(f"Error posting to Instagram: {str(e)}")
            if hasattr(e.response, 'json'):
                logging.error(f"API Response: {e.response.json()}")
            raise

    def post_text(self, text: str) -> Dict[str, Any]:
        """
        Post text to Instagram using a default image.
        
        Args:
            text: Text content to post
            
        Returns:
            Dict containing the API response
        """
        default_image = "media/default_image.jpg"
        return self.post_image(default_image, text)

    def get_account_info(self) -> Dict[str, Any]:
        """
        Get Instagram account information.
        
        Returns:
            Dict containing account information
        """
        try:
            url = f"{self.base_url}/{self.account_id}"
            params = {
                "fields": "id,username,profile_picture_url",
                "access_token": self.access_token
            }
            
            response = requests.get(url, params=params)
            response.raise_for_status()
            
            return response.json()
            
        except requests.exceptions.RequestException as e:
            logging.error(f"Error getting Instagram account info: {str(e)}")
            if hasattr(e.response, 'json'):
                logging.error(f"API Response: {e.response.json()}")
            raise 