# Proposed Architecture
The system flow will work based on clean layers:

Controller: Receives the message/image and dispatches it to the stream.

AI Orchestrator (The Brain): Controls the agent loop. It decides whether to call a Tool, whether to run the RAG, or whether to interact with the user.

Vector Service (RAG): Transforms user input into embedding (via OpenAI) and searches for similarity using native MySQL 9 queries.

Tools / Function Calling: Functions that the agent can invoke (e.g.searchWebViaOpenAI(), ,checkSefazStatus() ).

# File Structure (Laravel 13)
It is recommended to organize the structure within the directoryapp/ by isolating the AI ecosystem in a dedicated namespace (e.g.app/Services/AI orapp/AI):

``` Plaintext
app/
├── AI/
│   ├── Agents/
│   │   └── SupportAgent.php          # Orquestrador do loop do agente
│   ├── Embeddings/
│   │   └── OpenAIEmbeddingClient.php # Gerador de vetores (text-embedding-3-small)
│   ├── Tools/
│   │   ├── BaseTool.php
│   │   ├── WebSearchTool.php         # Tool para acionar API externa (ChatGPT/Web)
│   │   └── SefazCheckerTool.php      # Exemplo de tool futura de consulta SEFAZ
│   └── PromptTemplates/
│       └── SupportPrompt.php         # Centraliza o System Prompt estruturado
├── Http/
│   ├── Controllers/
│   │   └── ChatController.php        # Recebe o prompt do usuário do chat
│   └── Requests/
│       └── MessageRequest.php
└── Models/
├── SolutionDocument.php          # Model de documentos (guias, históricos) com vetor
├── ChatConversation.php          # Histórico da sessão do chat
└── ChatMessage.php               # Mensagens individuais (user, assistant, tool)
```

---

# Connections, Migrations and Database (MySQL 9)
MySQL 9 introduced support for vector operations. To store your document embeddings (Search Level 1), your migration must store the generated vector (usually 1536 dimensions if using OpenAI).

Example of Migration to RAG in MySQL 9:
``` PHP
Schema::create('solution_documents', function (Blueprint $table) {
$table->id();
$table->string('software'); // Home ou Stella
$table->string('title');
$table->longText('content'); // Erro + Solução anotada
// MySQL 9 adicionou o tipo VECTOR. Definimos a dimensão (ex: 1536 da OpenAI)
$table->vector('embedding', 1536)->nullable();
$table->timestamps();
});
```

---

# Vector Similarity Query (Laravel Service):
In your RAG Service, to fetch the closest error using MySQL's native cosine distance (or dot product) 9:

``` PHP
$embeddingJson = json_encode($userMessageEmbedding);

// Exemplo de query usando as novas funções vetoriais do MySQL 9
$results = DB::table('solution_documents')
->select('id', 'title', 'content')
->selectRaw('VECTOR_DISTANCE(embedding, VECTOR_FROM_TEXT(?)) AS distance', [$embeddingJson])
->where('software', $currentSoftware)
->orderBy('distance', 'asc')
->limit(3)
->get();
```

---

# Technical Development Prompt (For Code/Copilot AI)
Use the prompt below in code generation tools (or to guide your own development) to generate the skeletons of the fundamental classes:

``` Plaintext
Atue como um Engenheiro de Software Sênior especialista em Laravel 13, MySQL 9 e Engenharia de IA. Preciso implementar a estrutura de back-end para um Agente de Suporte Inteligente dos softwares "Home" e "Stella".

### REQUISITOS TÉCNICOS:
1. Framework: Laravel 13 (PHP 8.4+).
2. Banco de Dados: MySQL 9 utilizando o tipo primitivo `VECTOR` e a função `VECTOR_DISTANCE` para busca vetorial de documentos de suporte (RAG).
3. Arquitetura: Baseada em Services, isolando a lógica do Agente do Controller.
4. Funcionalidades do Agente: Deve suportar histórico de conversas (contexto), injeção de documentos via RAG (Nível 1) e Function Calling/Tools para busca externa na OpenAI se o RAG não encontrar resultados (Nível 2).

### ENTREGÁVEIS SOLICITADOS:
1. **Migration do MySQL 9**: Crie a migration da tabela `solution_documents` contendo o campo vetorial de 1536 dimensões para os embeddings.
2. **VectorSearchService**: Um serviço em Laravel que receba um texto, envie para a API da OpenAI para gerar o embedding, e execute a query SQL nativa do MySQL 9 usando `VECTOR_DISTANCE` para trazer os 3 documentos mais semelhantes.
3. **SupportAgent (Orquestrador)**: Uma classe que gerencie o ciclo de vida da conversa. Ela deve ler a mensagem do usuário, rodar o `VectorSearchService`, injetar o resultado no System Prompt. Se os resultados forem insuficientes, ela deve acionar dinamicamente uma Tool chamada `WebSearchTool` que simula a chamada externa da API da OpenAI (ChatGPT), mas somente SE o usuário autorizar explicitamente no chat.

Gere o código seguindo as melhores práticas do Laravel 13 (regras de Type Hinting estrito, readonly properties, e injeção de dependências via construtor). O código deve ser modular, testável e preparado para futuras extensões (ex: adição de novas Tools).
```
---
