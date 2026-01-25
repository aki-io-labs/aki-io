from aki_io import Aki
import json

def main():
    aki = Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37')

    chat_context = [
        {"role": "user", "content": "Hi! How are you?"},
        {"role": "assistant", "content": "I'm doing well, thank you! How can I help you today?"}
    ]

    params = {
        "prompt_input": "Tell me a joke",
        "chat_context": json.dumps(chat_context),   # dump the chat context to a string
        "top_k": 40,
        "top_p": 0.9,
        "temperature": 0.8,
        "max_gen_tokens": 1000
    }

    try:
        
        result = aki.do_api_request(params) # Do the API call and wait for result
        print("API response:\n", result['text'])
        
    except Exception as e:
        print(e)

if __name__ == "__main__":
    main()