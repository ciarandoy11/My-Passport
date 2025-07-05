import os
import schedule
import time
import random
import atexit
from datetime import datetime
from dotenv import load_dotenv
from content_generator import ContentGenerator
from social_media_poster import SocialMediaPoster
from config import CONTENT_SETTINGS

# Load environment variables
load_dotenv()

class SocialMediaAutomation:
    def __init__(self):
        self.content_generator = ContentGenerator(os.getenv("OPENAI_API_KEY"))
        self.social_media_poster = SocialMediaPoster()
        self.last_post_time = {}
        
        # Register cleanup function
        atexit.register(self.cleanup)

    def cleanup(self):
        """Cleanup resources before exit"""
        try:
            if hasattr(self.social_media_poster, 'tiktok') and self.social_media_poster.tiktok:
                self.social_media_poster.tiktok.shutdown()
        except Exception as e:
            print(f"Error during cleanup: {e}")

    def generate_and_post_content(self):
        """Generate and post content to social media platforms"""
        current_time = datetime.now()
        
        # Check if we've exceeded daily post limit
        posts_today = sum(1 for time in self.last_post_time.values() 
                         if time.date() == current_time.date())
        
        if posts_today >= CONTENT_SETTINGS["max_posts_per_day"]:
            print("Daily post limit reached")
            return

        # Select random platform and content type
        platform = random.choice(CONTENT_SETTINGS["platforms"])
        content_type = random.choice(CONTENT_SETTINGS["content_types"])
        
        # Skip TikTok for now if it's selected
        if platform == "tiktok":
            print("TikTok posting is temporarily disabled")
            return
        
        # Check minimum time between posts
        if platform in self.last_post_time:
            time_since_last_post = (current_time - self.last_post_time[platform]).total_seconds() / 3600
            if time_since_last_post < CONTENT_SETTINGS["min_time_between_posts"]:
                print(f"Waiting for minimum time between posts on {platform}")
                return

        try:
            # Generate content
            content = self.content_generator.generate_content(platform, content_type)
            if not content:
                print(f"Failed to generate content for {platform}")
                return

            # Post content
            success = self.social_media_poster.post_content(platform, content)
            if success:
                self.last_post_time[platform] = current_time
                print(f"Successfully posted to {platform}")
            else:
                print(f"Failed to post to {platform}")

        except Exception as e:
            print(f"Error in generate_and_post_content: {e}")
            import traceback
            print(traceback.format_exc())

    def run(self):
        """Run the social media automation system"""
        print("Starting social media automation system...")
        
        try:
            # Schedule posts
            schedule.every(1).hours.do(self.generate_and_post_content)
            
            # Run immediately on startup
            self.generate_and_post_content()
            
            # Keep the script running
            while True:
                schedule.run_pending()
                time.sleep(60)
        except KeyboardInterrupt:
            print("\nShutting down gracefully...")
            self.cleanup()
        except Exception as e:
            print(f"Error in run loop: {e}")
            self.cleanup()

if __name__ == "__main__":
    automation = SocialMediaAutomation()
    automation.run() 