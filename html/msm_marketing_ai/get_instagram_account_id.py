import requests
from config import INSTAGRAM_CONFIG

def get_instagram_account_id():
    access_token = INSTAGRAM_CONFIG['token']
    base_url = "https://graph.facebook.com/v18.0"
    
    try:
        # First, get the Facebook page ID
        url = f"{base_url}/me/accounts"
        params = {
            "access_token": access_token
        }
        
        response = requests.get(url, params=params)
        response.raise_for_status()
        pages = response.json()['data']
        
        if not pages:
            print("No Facebook pages found. Make sure you have a Facebook page connected to your Instagram account.")
            return
            
        # Get the first page ID
        page_id = pages[0]['id']
        print(f"Found Facebook page ID: {page_id}")
        
        # Now get the Instagram account ID
        url = f"{base_url}/{page_id}"
        params = {
            "fields": "instagram_business_account",
            "access_token": access_token
        }
        
        response = requests.get(url, params=params)
        response.raise_for_status()
        instagram_account_id = response.json()['instagram_business_account']['id']
        
        print(f"\nYour Instagram account ID is: {instagram_account_id}")
        print("\nAdd this ID to your config.py file in the INSTAGRAM_CONFIG dictionary:")
        print(f'"account_id": "{instagram_account_id}"')
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {str(e)}")
        if hasattr(e.response, 'json'):
            print(f"API Response: {e.response.json()}")

if __name__ == "__main__":
    get_instagram_account_id() 