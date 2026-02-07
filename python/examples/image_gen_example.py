import base64

from aki_io import Aki

aki = Aki(
    'z_image_turbo',
    'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37',    
)

def progress_callback(progress, progress_data):
    print(f'Progress: {progress.get("progress", 0)}%')

result = aki.do_api_request(
    {
        'prompt': 'Astronaut on Mars holding a banner which states "AKI is happy to serve your model" during sunset sitting on a giant yellow rubber duck',
        'seed': -1,
        'height': 512,
        'width': 512,    
    },
    progress_callback # optional
)

if result['success']:
    # Save image to file
    base64_images = result.get('images')
    for idx, base64_image in enumerate(base64_images):
        output_file = f'image_{idx}.png'
        with open(output_file, 'wb') as f:
            f.write(base64.b64decode(base64_image.split(',')[1]))
        print(f'Output image saved at {output_file}.')
else:
    print("API Error:", result.get('error_code'), "-", result.get('error'))

