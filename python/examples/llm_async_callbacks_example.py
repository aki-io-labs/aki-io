import asyncio
from aki_io import Aki

aki = Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

chat_context = [
    {"role": "system", "content": "You are a helpful assistant named AKI."},
    {"role": "assistant", "content": "How can I help you today?"},
    {"role": "user", "content": "Tell me a funny store with more than 100 words"},
]

params = {
    "chat_context": chat_context,
    "top_k": 40,
    "top_p": 0.9,
    "temperature": 0.8,
    "max_gen_tokens": 4000,
}

output_position = 0

def progress_callback(progress, progress_data):
    global output_position
    if progress_data and 'text' in progress_data:
        text = progress_data.get('text')
        print(text[output_position:], end='', flush=True)
        output_position = len(text)

def result_callback(result):
    if result['success']:
        print(result.get('text')[output_position:], end='', flush=True)    
        print("\n\nGenerated Tokens:", result['num_generated_tokens'], )
    else:
        print("API Error:", result.get('error_code'), "-", result.get('error'))            


# use create_task() instead of run() to fire in background
asyncio.run(aki.do_api_request_async(
        params,
        result_callback,
        progress_callback # optional
    )) 


asyncio.run(aki.close_session())
