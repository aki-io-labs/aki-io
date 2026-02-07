from aki_io import Aki

aki = Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

chat_context = [
    {"role": "user", "content": "Hi! How are you?"},
    {"role": "assistant", "content": "I'm doing well, thank you! How can I help you today?"}
]

params = {
    "prompt_input": "Tell me a joke",
    "chat_context": chat_context,
    "top_k": 40,
    "top_p": 0.9,
    "temperature": 0.8,
    "max_gen_tokens": 1000
}

    
result = aki.do_api_request(params) # Do the API call and wait for result
if result['success']:
    print("API JSON response:\n", result)
    print("\nChat response:\n", result['text'])
    print("\nGenerated Tokens:", result['num_generated_tokens'], )
else:
    print("API Error:", result.get('error_code'), "-", result.get('error'))
