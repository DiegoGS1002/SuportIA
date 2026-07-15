<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# SuportIA

SuportIA e um agente de suporte tecnico inteligente para os softwares **Home** e **Stella** da Data C. O sistema recebe mensagens (texto e/ou prints de tela), diagnostica erros operacionais, falhas de sistema e rejeicoes fiscais (SEFAZ), buscando primeiro na base de conhecimento interna (RAG) e, mediante consentimento explicito do usuario, em fontes externas via OpenAI.

O comportamento operacional do agente e definido em [`prompt/AgenteIA.md`](prompt/AgenteIA.md) (prompt canonico) e a arquitetura tecnica esta descrita em [`prompt/Estructure.md`](prompt/Estructure.md).

## Stack

- **Laravel 13** (PHP 8.3+)
- **MySQL** para persistencia, com suporte planejado ao tipo nativo `VECTOR` do MySQL 9 (`VECTOR_DISTANCE`, `VECTOR_FROM_TEXT`) e fallback automatico em JSON + calculo de distancia cosseno em PHP para versoes anteriores do MySQL ou outros drivers (ex.: sqlite em testes)
- **openai-php/client** para chat completions, embeddings e visao (OCR de prints)
- **Vue 3 + Vite** no front-end (`resources/js`)

## Arquitetura do Agente de IA

```
app/
├── AI/                              # Ecossistema de IA (orquestracao, prompts e tools)
│   ├── Agents/
│   │   └── SupportAgent.php         # Orquestrador do loop do agente (o "cerebro")
│   ├── Embeddings/
│   │   └── OpenAIEmbeddingClient.php # Gera embeddings (text-embedding-3-*)
│   ├── PromptTemplates/
│   │   └── SupportPrompt.php        # Monta o System Prompt (AgenteIA.md + contexto RAG)
│   └── Tools/
│       ├── ToolInterface.php / BaseTool.php
│       ├── WebSearchTool.php        # Busca externa (Nivel 2), apenas com consentimento
│       └── SefazCheckerTool.php     # Exemplo de tool futura de consulta SEFAZ
├── Services/
│   ├── AI/
│   │   ├── AiServiceInterface.php   # Contrato de chat completion + function calling
│   │   ├── AiChatResponse.php       # DTO da resposta normalizada do provider
│   │   ├── OpenIAService.php        # Implementacao via OpenAI Chat Completions
│   │   └── PromptManager.php        # Monta o array de mensagens (system + historico)
│   ├── Search/
│   │   ├── VectorSearchService.php  # RAG Nivel 1: busca vetorial na base interna
│   │   └── WebSearchService.php     # RAG Nivel 2: pesquisa externa via OpenAI
│   └── Vision/
│       └── OCRService.php           # Extrai texto de prints/screenshots (Vision)
├── Http/
│   ├── Controllers/Api/
│   │   └── ChatController.php       # Endpoints REST do chat
│   └── Requests/
│       ├── ChatStoreRequest.php
│       └── ChatMessageRequest.php
└── Models/
    ├── Chat.php                     # Conversa (software, status, historico)
    ├── ChatMessage.php              # Mensagem individual (user, assistant, tool, system)
    └── KnowledgeBase.php            # Documentos de solucao com embedding vetorial
```

### Fluxo de resolucao (`SupportAgent`)

1. **Coleta**: a mensagem do usuario (e o texto extraido via OCR, se houver imagem) e persistida como `ChatMessage`.
2. **Busca interna (RAG - Nivel 1)**: o `VectorSearchService` gera o embedding da mensagem e busca os documentos mais similares em `knowledge_bases`, filtrados por software (Home/Stella).
3. **Resposta com contexto**: se houver documentos relevantes, o `SupportPrompt` injeta esse contexto no system prompt e a resposta e gerada pela `OpenIAService`.
4. **Sem resultado interno**: se nada for encontrado, o agente responde pedindo consentimento explicito para pesquisa externa, conforme definido em `prompt/AgenteIA.md`.
5. **Busca externa (Nivel 2)**: com consentimento, o `WebSearchTool` e disponibilizado ao modelo via function calling e aciona o `WebSearchService`.
6. **Recusa**: se o usuario recusar a busca externa, o atendimento e encerrado com a mensagem padrao.

## Endpoints da API

| Metodo | Rota                        | Descricao                                   |
|--------|------------------------------|----------------------------------------------|
| GET    | `/api/chats`                 | Lista as conversas                            |
| POST   | `/api/chats`                 | Cria uma nova conversa (`software`, `title`)  |
| GET    | `/api/chats/{chat}`          | Detalhes de uma conversa com o historico      |
| POST   | `/api/chats/{chat}/messages` | Envia mensagem/imagem e recebe a resposta do agente |
| DELETE | `/api/chats/{chat}`          | Exclui uma conversa e todas as suas mensagens |
| GET    | `/api/knowledge-bases`       | Lista os documentos da base de conhecimento   |
| POST   | `/api/knowledge-bases`       | Faz upload e indexa um documento (PDF, Word, TXT) |
| DELETE | `/api/knowledge-bases/{id}`  | Exclui um documento da base de conhecimento   |

O envio de mensagem aceita `message` (texto), `image` (print, opcional), `external_search_consent` e `external_search_declined` (booleanos, usados no fluxo de consentimento da busca externa).

O upload de documentos aceita `software` (home/stella), `title` e `file` (PDF, DOC, DOCX ou TXT). O texto e extraido automaticamente e indexado com embeddings OpenAI para uso no RAG (Nivel 1).

## Configuracao

1. Copie o arquivo de ambiente e gere a chave da aplicacao:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. Configure a conexao com o MySQL (`DB_*`) e as credenciais da OpenAI no `.env`:

   ```dotenv
   OPENAI_API_KEY=
   OPENAI_MODEL=gpt-4o
   OPENAI_VISION_MODEL=gpt-4o
   OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small
   OPENAI_EMBEDDINGS_DIMENSIONS=1536
   ```

3. Instale as dependencias e rode as migrations:

   ```bash
   composer install
   npm install
   php artisan migrate
   ```

4. Suba o ambiente de desenvolvimento (servidor, queue, logs e Vite):

   ```bash
   composer dev
   ```

## Testes

O projeto usa [Pest](https://pestphp.com/):

```bash
composer test
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


