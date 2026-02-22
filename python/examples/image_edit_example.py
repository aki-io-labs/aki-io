import base64

from aki_io import Aki

aki = Aki(
    'qwen_image_edit',
    'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37',
)

def progress_callback(progress, progress_data):
    print(f'Progress: {progress.get("progress", 0)}%')


# read image created with image_gen_example.py (run first to get an example image)
with open('image_0.jpeg', 'rb') as f:
    input_image = f.read()

result = aki.do_api_request(
    {
        'prompt': 'replace astronaut with ice bear',
        'seed': -1,
        'height': 512,
        'width': 512,
        'image': Aki.encode_binary(input_image, 'jpeg')
    },
    progress_callback, # optional
)

if result['success']:
    # Save images to files
    images = result.get('images')
    for idx, image_data in enumerate(images):
        media_format, image_binary = Aki.decode_binary(image_data)
        output_file = f'image_edit_{idx}.{media_format}'
        with open(output_file, 'wb') as f:
            f.write(image_binary)
        print(f'Output image saved at {output_file}')
else:
    print("API Error:", result.get('error_code'), "-", result.get('error'))

