import re

with open('C:/xampp/htdocs/ecom/admin/inc/AI/Providers/GeminiProvider.php', 'r', encoding='utf-8') as f:
    c = f.read()

old_content = """        if (!$apiKey) {
            // MOCK RESPONSE FOR TESTING PURPOSES WHEN NO API KEY IS PROVIDED
            return [
                'content' => 'مرحباً بك! نعم، السويت شيرت متوفر باللون الأسود ومقاس لارج. يمكنك إتمام الطلب الآن.',
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'cost' => 0.0001
                ]
            ];
        }"""

new_content = """        if (!$apiKey) {
            // MOCK RESPONSE FOR TESTING PURPOSES WHEN NO API KEY IS PROVIDED
            return [
                'content' => json_encode(['text' => 'مرحباً بك! نعم، السويت شيرت متوفر باللون الأسود ومقاس لارج. يمكنك إتمام الطلب الآن.']),
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'cost' => 0.0001
                ]
            ];
        }"""

c = c.replace(old_content, new_content)

with open('C:/xampp/htdocs/ecom/admin/inc/AI/Providers/GeminiProvider.php', 'w', encoding='utf-8') as f:
    f.write(c)
print('Fixed mock content format.')
