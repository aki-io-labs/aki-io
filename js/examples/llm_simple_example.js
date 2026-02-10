const { Aki, doAPIRequest } = require('../aki_io');

const ENDPOINT = 'llama3_8b_chat';
const API_KEY = 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37';

const chatContext = [
    {role: 'system', content: 'You are a helpful assistant named AKI.' },
    {role: 'assistant', content: 'How can I help you today?' },
    {role: 'user', content: 'Tell me a joke' }
];

const params = {
    chat_context: JSON.stringify(chatContext),
    top_k: 40,
    top_p: 0.9,
    temperature: 0.8,
    max_gen_tokens: 1000
};

doAPIRequest(
    ENDPOINT,
    API_KEY,
    params,
    (result) => {
        if (result.success) {
            console.log('\nAPI JSON response:', result);
            console.log('\nChat response:\n', result.text);
            console.log('\nGenerated Tokens:', result.num_generated_tokens);

        }
        else {
            console.error('API Error:', result.error_code, '-', result.error);
        }
    }
);
