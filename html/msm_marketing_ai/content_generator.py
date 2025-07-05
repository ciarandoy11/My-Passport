import requests
import json
import os
from datetime import datetime
from PIL import Image
import moviepy.editor as mp
import random
from config import MSM_API_BASE_URL, MSM_CREDENTIALS

class ContentGenerator:
    def __init__(self, openai_api_key):
        self.openai_api_key = openai_api_key
        self.msm_data = self._fetch_msm_data()

    def _fetch_msm_data(self):
        """Fetch data from My Sport Manager API"""
        try:
            # Fetch changelog
            changelog_response = requests.get(f"{MSM_API_BASE_URL}/ChangeLog.md")
            print(f"Changelog response status: {changelog_response.status_code}")
            print(f"Changelog response text: {changelog_response.text}")
            data = {
                "Change Log": changelog_response.text if changelog_response.ok else changelog_response.text
            }
            return data
        except Exception as e:
            print(f"Error fetching MSM data: {e}")
            return {}

    def generate_text_content(self, platform):
        """Generate text content for the specified platform"""
        prompt = f"""Create an engaging social media post about My Sport Manager for {platform}.
        Use the following information as inspiration:
        {json.dumps(self.msm_data, indent=2)}
        
        The post should be:
        - Engaging and platform-appropriate
        - Highlight the benefits of My Sport Manager
        - Include relevant hashtags
        - Be under 280 characters for Twitter, or appropriate length for {platform}
        """
        try:
            headers = {
                "Authorization": f"Bearer {self.openai_api_key}",
                "Content-Type": "application/json"
            }
            data = {
                "model": "gpt-4",
                "messages": [{"role": "user", "content": prompt}]
            }
            response = requests.post(
                "https://api.openai.com/v1/chat/completions",
                headers=headers,
                json=data
            )
            if response.status_code == 200:
                content = response.json()["choices"][0]["message"]["content"]
                if not content:
                    print("OpenAI API returned empty content")
                    return None
                return content
            else:
                error_msg = f"OpenAI API error: {response.status_code} - {response.text}"
                print(error_msg)
                return None
        except requests.exceptions.RequestException as e:
            print(f"Network error while calling OpenAI API: {e}")
            return None
        except Exception as e:
            print(f"Unexpected error generating text content: {e}")
            return None

    def generate_image_content(self, text_content):
        """Generate an image based on the text content"""
        prompt = f"""Create a professional image for a sports management app post with the following content:
        {text_content}
        The image should be:
        - Professional and modern
        - Sports-themed
        - Suitable for social media
        - Include the My Sport Manager logo
        """
        try:
            headers = {
                "Authorization": f"Bearer {self.openai_api_key}",
                "Content-Type": "application/json"
            }
            data = {
                "prompt": prompt,
                "n": 1,
                "size": "1024x1024"
            }
            response = requests.post(
                "https://api.openai.com/v1/images/generations",
                headers=headers,
                json=data
            )
            if response.status_code == 200:
                image_url = response.json()["data"][0]["url"]
                image_path = f"media/image_{datetime.now().strftime('%Y%m%d_%H%M%S')}.png"
                # Download and save the image
                img_response = requests.get(image_url)
                with open(image_path, 'wb') as f:
                    f.write(img_response.content)
                return image_path
            else:
                print(f"Error from OpenAI API: {response.status_code} {response.text}")
                return None
        except Exception as e:
            print(f"Error generating image content: {e}")
            return None

    def generate_video_content(self, text_content, image_path):
        """Generate a video based on the text content and image"""
        clip = mp.ImageClip(image_path)
        txt_clip = mp.TextClip(text_content, fontsize=70, color='white')
        txt_clip = txt_clip.set_position('center').set_duration(10)
        video = mp.CompositeVideoClip([clip, txt_clip])
        video_path = f"media/video_{datetime.now().strftime('%Y%m%d_%H%M%S')}.mp4"
        video.write_videofile(video_path, fps=24)
        return video_path

    def generate_content(self, platform, content_type="text"):
        """Generate content based on platform and type"""
        text_content = self.generate_text_content(platform)
        if content_type == "text":
            return {"text": text_content}
        elif content_type == "image":
            image_path = self.generate_image_content(text_content)
            return {"text": text_content, "image": image_path}
        elif content_type == "video":
            image_path = self.generate_image_content(text_content)
            video_path = self.generate_video_content(text_content, image_path)
            return {"text": text_content, "video": video_path}
        return None 