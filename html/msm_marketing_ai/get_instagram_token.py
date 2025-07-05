import requests
import json
from config import INSTAGRAM_CONFIG

def get_long_lived_token(short_lived_token):
    """Convert a short-lived token to a long-lived token"""
    url = "https://graph.facebook.com/v18.0/oauth/access_token"
    params = {
        "grant_type": "fb_exchange_token",
        "client_id": INSTAGRAM_CONFIG["app_id"],
        "client_secret": INSTAGRAM_CONFIG["app_secret"],
        "fb_exchange_token": short_lived_token
    }
    
    response = requests.get(url, params=params)
    if response.status_code == 200:
        return response.json()["access_token"]
    else:
        print(f"Error getting long-lived token: {response.text}")
        return None

def get_instagram_account_id(access_token):
    """Get the Instagram Business Account ID"""
    # First get the user's Facebook pages
    url = "https://graph.facebook.com/v18.0/me/accounts"
    params = {"access_token": access_token}
    
    print("Getting Facebook pages...")
    response = requests.get(url, params=params)
    if response.status_code != 200:
        print(f"Error getting Facebook pages: {response.text}")
        return None
        
    pages = response.json().get("data", [])
    if not pages:
        print("No Facebook pages found. Please create a Facebook Page and connect it to your Instagram account.")
        return None
        
    # Get the Instagram Business Account ID for each page
    for page in pages:
        page_id = page["id"]
        print(f"\nChecking page: {page['name']} (ID: {page_id})")
        
        url = f"https://graph.facebook.com/v18.0/{page_id}"
        params = {
            "access_token": access_token,
            "fields": "instagram_business_account"
        }
        
        response = requests.get(url, params=params)
        if response.status_code == 200:
            data = response.json()
            if "instagram_business_account" in data:
                instagram_id = data["instagram_business_account"]["id"]
                print(f"Found Instagram Business Account ID: {instagram_id}")
                return instagram_id
            else:
                print("No Instagram Business Account connected to this page")
        else:
            print(f"Error checking page: {response.text}")
    
    print("\nNo Instagram Business Account found. Please make sure:")
    print("1. Your Instagram account is a Business or Creator account")
    print("2. Your Instagram account is connected to a Facebook Page")
    print("3. You are an admin of the Facebook Page")
    return None

if __name__ == "__main__":
    print("Please follow these steps:")
    print("1. Go to https://developers.facebook.com/tools/explorer/")
    print("2. Select your app from the dropdown")
    print("3. Click 'Generate Access Token'")
    print("4. Request these permissions:")
    print("   - instagram_basic")
    print("   - instagram_content_publish")
    print("   - instagram_manage_comments")
    print("   - instagram_manage_insights")
    print("   - pages_show_list")
    print("   - pages_read_engagement")
    print("   - pages_manage_posts")
    print("   - pages_manage_metadata")
    print("\nEnter your short-lived access token:")
    short_lived_token = input().strip()
    
    long_lived_token = get_long_lived_token(short_lived_token)
    if long_lived_token:
        print("\nLong-lived token:")
        print(long_lived_token)
        
        account_id = get_instagram_account_id(long_lived_token)
        if account_id:
            print("\nInstagram Business Account ID:")
            print(account_id)
            
            print("\nAdd these to your .env file:")
            print(f"INSTAGRAM_ACCESS_TOKEN={long_lived_token}")
            print(f"INSTAGRAM_ACCOUNT_ID={account_id}")
        else:
            print("\nCould not get Instagram account ID. Please make sure:")
            print("1. Your Facebook app has the Instagram Graph API product added")
            print("2. Your Instagram account is connected to a Facebook Page")
            print("3. You have granted all the required permissions") 