WhatsappJUJU — Instruções de Instalação
========================================

REQUISITOS DO SERVIDOR
-----------------------
- PHP 7.4 ou superior (com extensão SQLite3/PDO_SQLite)
- Servidor Apache com mod_rewrite (para .htaccess)
- Acesso de escrita para criar o banco de dados em db/

INSTALAÇÃO VIA FTP
-------------------
1. Faça upload de toda a pasta 'sete/' para o seu servidor
   Exemplo: dominio.com/sete/  ou  dominio.com/ (raiz)

2. Certifique-se de que a pasta 'db/' tem permissão de escrita (chmod 755 ou 775)
   Também configure 'uploads/avatars/' e 'uploads/files/' com chmod 755

3. Acesse no navegador: https://seu-dominio.com/sete/

4. Na PRIMEIRA VISITA, o banco de dados é criado automaticamente.

5. Faça login com o admin padrão:
   E-mail: admin@sete.app
   Senha:  admin123

   IMPORTANTE: Na primeira vez, você será obrigado a trocar a senha!

6. No Painel Admin (ícone ⚙️ no header), configure sua IA:
   - Escolha o provider (OpenRouter, Groq, Gemini, etc.)
   - Insira sua API Key
   - Escolha o modelo
   - Clique em "Testar Conexão" para verificar
   - Salve as configurações

7. Clique em "Contatos" > "+ Novo Personagem" para criar seus primeiros contatos IA.

PERMISSÕES DE PASTAS
---------------------
db/             → 755 (PHP precisa criar e escrever no sete.db)
uploads/        → 755
uploads/avatars/→ 755
uploads/files/  → 755

PROVIDERS DE IA SUPORTADOS
----------------------------
- OpenRouter   → https://openrouter.ai  (tem opções gratuitas)
- Groq         → https://console.groq.com
- Google Gemini→ https://aistudio.google.com
- Ollama       → http://localhost:11434 (local, sem API key)
- OpenAI       → https://platform.openai.com
- Mistral AI   → https://console.mistral.ai
- Together AI  → https://api.together.xyz

TROUBLESHOOTING
----------------
- Erro "Permission denied" ao criar banco: ajuste chmod da pasta db/
- Erro 500 após upload: verifique se a extensão fileinfo está ativa no PHP
- IA não responde: verifique a API Key e o modelo no painel admin
- Vozes não funcionam: use Chrome ou Edge (melhor suporte a SpeechSynthesis)
- SSE não funciona: verifique se o servidor não tem limite de tempo muito curto

FUNCIONALIDADES
----------------
✓ Chat com personagens IA customizáveis
✓ Streaming de resposta em tempo real (SSE)
✓ 5 temas visuais
✓ Upload de imagens e documentos
✓ Text-to-Speech (voz do navegador) em cada mensagem
✓ Speech-to-Text (reconhecimento de voz)
✓ Perfil de usuário com foto
✓ Painel admin (gerenciar usuários, configurar IA, ver estatísticas)
✓ Suporte a Markdown nas mensagens
✓ Design responsivo mobile/desktop
✓ Banco SQLite (zero configuração de MySQL)

SEGURANÇA
----------
- Banco de dados protegido por .htaccess (não acessível via web)
- Uploads verificados por MIME type real (não só extensão)
- Prepared statements em todas as queries SQL
- Sessões PHP com regeneração de ID no login
- Rate limiting simples (30 req/minuto por sessão)
- Senhas com bcrypt (password_hash)

Versão: 1.0.0
Site: WhatsappJUJU
