import json
import base64
from pathlib import Path
from aki_io import Aki

def generate_image():
    
    # Define the image generation parameters
    params = {
        'prompt': 'Astronaut on Mars holding a banner which states "AKI is happy to serve your model" during sunset sitting on a giant yellow rubber duck',
        'seed': -1,
        'height': 512,
        'width': 512,
        'steps': 40,
        'provide_progress_images': 'none',
        'wait_for_result': True
    }

    # Call the AKI API
    result = do_aki_request(
        endpoint_name='qwen_image',
        api_key='fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37',
        params,
    )

    # Save the images
    images = result.get('images') or result.get('job_result', {}).get('images', [])
    if not images:
        print("No images returned by the API.")
        return result
    for i, img_b64 in enumerate(images):
        header, img_data = img_b64.split(',', 1) if ',' in img_b64 else (None, img_b64)
        img_bytes = base64.b64decode(img_data)
        filename = Path(__file__).parent / f'image_{i}.png'
        filename.write_bytes(img_bytes)
        print(f"Saved image to: {filename}")
    print(f"\nImage generation complete. {len(images)} image(s) saved.")
    return result

if __name__ == "__main__":
    generate_image()