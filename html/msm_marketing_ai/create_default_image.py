from PIL import Image, ImageDraw, ImageFont
import os

def create_default_image():
    # Create media directory if it doesn't exist
    if not os.path.exists('media'):
        os.makedirs('media')
    
    # Create a new image with a white background
    width = 1080
    height = 1080
    image = Image.new('RGB', (width, height), 'white')
    draw = ImageDraw.Draw(image)
    
    # Add a gradient background
    for y in range(height):
        r = int(255 * (1 - y/height))
        g = int(200 * (1 - y/height))
        b = int(150 * (1 - y/height))
        for x in range(width):
            draw.point((x, y), fill=(r, g, b))
    
    # Add text
    try:
        font = ImageFont.truetype("arial.ttf", 60)
    except:
        font = ImageFont.load_default()
    
    text = "My Sport Manager"
    text_width = draw.textlength(text, font=font)
    text_position = ((width - text_width) // 2, height // 2)
    
    # Add text shadow
    shadow_offset = 3
    draw.text((text_position[0] + shadow_offset, text_position[1] + shadow_offset), 
              text, font=font, fill='black')
    
    # Add main text
    draw.text(text_position, text, font=font, fill='white')
    
    # Save the image
    image.save('media/default_image.jpg', 'JPEG')
    print("Default image created successfully at media/default_image.jpg")

if __name__ == "__main__":
    create_default_image() 