# Auditoria do Sistema SuportIA

Data da auditoria: 06/10/2026  
Escopo principal: entrada e processamento de arquivos, conversa com o chat, segurança, testes e infraestrutura.

## Resumo executivo

O sistema foi auditado e as melhorias prioritárias descritas neste documento foram implementadas em 06/10/2026. Os fluxos de chat e documentos agora possuem autenticação, autorização por proprietário, arquivos privados, processamento assíncrono e estados explícitos de entrega/processamento.

## Status da implementação

Implementado:

- Correção do consentimento e da recusa de pesquisa externa, sem mensagens ou embeddings vazios.
- Pesquisa real na web com fontes, URLs clicáveis e metadados persistidos.
- Autenticação por sessão, configuração segura do primeiro administrador, policies, escopo por usuário e rate limiting.
- Armazenamento privado de uploads, prints e PDFs exportados, com downloads autorizados.
- Validação unificada: documentos PDF, DOCX, TXT e Markdown (`.md`) até 20 MB; imagens JPEG, PNG e WebP até 10 MB.
- Processamento de documentos em fila, embeddings em lote, limite de chunks, retries e estados `uploaded`, `processing`, `indexed`, `needs_ocr` e `failed`.
- OCR de PDFs escaneados, com limite de páginas, e persistência privada das imagens enviadas no chat.
- Listagem e exclusão pelo documento original, com remoção em cascata dos chunks.
- Histórico limitado com resumo das mensagens antigas, idempotência de envio e estados `pending`, `completed` e `failed`.
- Preservação do texto/anexo quando o envio falha e exibição das fontes e do print original na conversa.
- Mensagens de erro externas genéricas, nomes UUID para exportações e escape do conteúdo dos PDFs.
- Testes de consentimento, idempotência, autenticação, isolamento entre usuários e exclusão completa de documentos.

Itens operacionais opcionais que permanecem recomendados:

- Integrar antivírus dedicado, como ClamAV, caso o sistema aceite arquivos de ambientes não confiáveis.
- Adicionar paginação visual para carregar mensagens anteriores e indicador percentual de progresso dos jobs.
- Adicionar testes de integração com amostras reais de PDF escaneado e DOCX complexo no pipeline de CI.

## Verificações da auditoria original

- Containers `app`, `nginx` e `mysql`: ativos.
- MySQL: saudável.
- Migrations: todas aplicadas.
- Build Vue/Vite: concluído com sucesso.
- Sintaxe PHP: nenhum erro encontrado.
- Testes no container: 12 testes aprovados, com 28 asserções.
- Testes executados diretamente no host: 8 falhas por permissões no diretório `storage/framework/testing`.
- Laravel Pint: falhou devido a inconsistências de formatação em diversos arquivos.
- O arquivo `docker-compose.yml` já possuía uma modificação local, que não foi alterada pela auditoria.

## Erros críticos

### 1. Fluxo de consentimento do chat quebrado

Ao autorizar a pesquisa externa, o front-end envia uma mensagem vazia. O agente persiste essa mensagem e tenta gerar embedding de uma string vazia.

Arquivos relacionados:

- `resources/js/Composables/useChat.js`, método `authorizeExternalSearch`.
- `app/AI/Agents/SupportAgent.php`, método `handle`.
- `app/Services/Search/VectorSearchService.php`, método `search`.

O erro foi confirmado nos logs:

```text
Cannot generate an embedding for an empty text.
```

Ao recusar a busca externa, a validação continua exigindo o campo `message`, porque a regra considera somente `external_search_consent` e ignora `external_search_declined`.

Arquivo relacionado:

- `app/Http/Requests/ChatMessageRequest.php`.

Ajustes necessários:

- Tratar autorização e recusa como eventos separados de uma mensagem comum.
- Não persistir mensagens vazias.
- Não tentar gerar embedding de texto vazio.
- Recuperar a consulta original que motivou o pedido de consentimento.
- Adicionar testes para autorização, recusa e imagem sem texto reconhecido.

### 2. APIs sem autenticação e autorização efetivas

As rotas da API não possuem middleware de autenticação. Os `FormRequest` autorizam todas as solicitações e os controllers não verificam a propriedade dos chats.

Arquivos relacionados:

- `routes/api.php`.
- `app/Http/Controllers/Api/ChatController.php`.
- `app/Http/Controllers/Api/DocumentController.php`.
- `app/Http/Controllers/Api/KnowledgeBaseController.php`.
- `app/Http/Requests/ChatMessageRequest.php`.
- `app/Http/Requests/ChatStoreRequest.php`.
- `app/Http/Requests/KnowledgeBaseStoreRequest.php`.

Impactos:

- Um cliente pode visualizar chats de terceiros caso conheça seus IDs.
- Chats podem ser alterados, exportados ou excluídos sem verificação de propriedade.
- Quando não existe usuário autenticado, as listagens podem retornar todos os registros.
- As rotas que consomem OpenAI podem ser abusadas e gerar custos.

Ajustes necessários:

- Adicionar middleware de autenticação.
- Criar policies para chats e documentos.
- Aplicar escopo obrigatório pelo usuário autenticado.
- Adicionar rate limiting nas rotas de chat, OCR, upload e pesquisa externa.

### 3. Documentos armazenados publicamente

Uploads e PDFs exportados são gravados no disco `public`.

Arquivos relacionados:

- `app/Http/Controllers/Api/KnowledgeBaseController.php`.
- `app/Services/ChatToPdfExporter.php`.
- `config/filesystems.php`.

Caso o link público do Laravel esteja habilitado, os documentos podem ser acessados diretamente sem passar pela autorização do controller.

Ajustes necessários:

- Armazenar documentos no disco privado.
- Realizar downloads somente por endpoints autorizados.
- Utilizar URLs temporárias quando aplicável.
- Avaliar criptografia em repouso para arquivos sensíveis.

## Entrada e processamento de arquivos

### 4. Limites de upload contraditórios

Os limites encontrados foram:

| Camada | Limite |
|---|---:|
| Front-end | 10 MB |
| Laravel | 100 MB |
| PHP | 100 MB |
| Nginx | 50 MB |

Arquivos relacionados:

- `resources/js/Pages/Documentos.vue`.
- `app/Http/Requests/KnowledgeBaseStoreRequest.php`.
- `docker/php/php.ini`.
- `docker/nginx/default.conf`.

Na prática, o comportamento varia conforme o tamanho e o ponto em que o arquivo é recusado.

Ajuste necessário: definir um único limite e aplicá-lo no front-end, Laravel, PHP e Nginx.

### 5. Arquivos `.doc` são aceitos, mas não processados

A validação aceita `.doc`, porém o processador somente possui implementação para DOCX. Arquivos DOC são salvos sem conteúdo indexável.

Arquivos relacionados:

- `app/Http/Requests/KnowledgeBaseStoreRequest.php`.
- `app/Services/DocumentProcessor.php`.

Ajustes possíveis:

- Remover `.doc` da lista de formatos aceitos; ou
- Converter o arquivo com LibreOffice ou outra ferramenta executada em ambiente isolado.

### 6. PDFs escaneados não entram no RAG

Quando o PDF não contém texto extraível, o sistema retorna HTTP 201 e salva o arquivo, mas não cria chunks nem embeddings. Apesar da resposta de sucesso, o chat nunca encontrará o conteúdo.

Arquivo relacionado:

- `app/Http/Controllers/Api/KnowledgeBaseController.php`.

Ajustes necessários:

- Criar estados como `uploaded`, `processing`, `indexed`, `needs_ocr` e `failed`.
- Executar OCR em PDFs escaneados.
- Exibir o estado de processamento na interface.
- Não apresentar um arquivo sem embeddings como documento indexado com sucesso.

### 7. Um documento aparece como vários documentos

A listagem da base consulta diretamente a tabela `knowledge_bases`. Dessa forma, cada chunk aparece como um cartão independente com títulos como “Parte 1” e “Parte 2”.

Além disso, a exclusão remove somente um chunk e mantém os demais chunks e o arquivo original.

Arquivo relacionado:

- `app/Http/Controllers/Api/KnowledgeBaseController.php`.

Ajustes necessários:

- Listar registros da tabela `documents`.
- Manter os chunks como detalhes internos do documento.
- Excluir o documento original e deixar o relacionamento em cascata remover todos os chunks.
- Retornar na API o estado, quantidade de chunks, software, título e eventual erro de processamento.

### 8. Processamento síncrono de arquivos grandes

O sistema pode aceitar arquivos de até 100 MB, extrair todo o conteúdo e realizar uma chamada à OpenAI para cada chunk dentro da requisição HTTP.

Riscos:

- Timeout no Nginx ou PHP.
- Consumo excessivo de memória.
- Muitas chamadas à OpenAI.
- Custo elevado.
- Documento parcialmente processado em caso de falha.

Ajustes necessários:

- Processar documentos em fila.
- Definir limite de páginas, caracteres e chunks.
- Gerar embeddings em lote.
- Implementar retry com backoff.
- Exibir progresso no front-end.
- Registrar o erro de processamento sem retornar detalhes internos ao usuário.

### 9. Imagens enviadas pelo chat não são persistidas

O print é enviado para o OCR, mas apenas a informação `has_image: true` é armazenada na mensagem. A imagem original não é preservada.

Arquivo relacionado:

- `app/AI/Agents/SupportAgent.php`.

Impactos:

- A imagem não pode ser visualizada após recarregar a conversa.
- Não é possível auditar o resultado do OCR.
- Não é possível reprocessar uma imagem em caso de falha.

Ajustes necessários:

- Armazenar o arquivo em disco privado.
- Relacionar o arquivo à mensagem.
- Salvar separadamente o texto extraído e a mensagem escrita pelo usuário.
- Aplicar validação de tipo, dimensões, tamanho e conteúdo no front-end e backend.

### 10. Validação e extração de documentos precisam ser reforçadas

Pontos observados:

- O front-end aceita formato pela extensão quando o MIME não é reconhecido.
- O processador DOCX concatena nós de texto sem preservar adequadamente parágrafos e espaços.
- O chunking divide por quantidade de caracteres e pode cortar palavras e etapas de uma solução.
- Não há proteção clara contra arquivos compactados maliciosos ou DOCX com expansão excessiva.
- Trechos de conteúdo de PDF são registrados nos logs, o que pode expor dados sensíveis.

Melhorias recomendadas:

- Validar assinatura real do arquivo, MIME e extensão.
- Definir limites de descompactação para DOCX.
- Separar chunks por títulos, parágrafos e sentenças.
- Remover conteúdo de documentos dos logs ou aplicar mascaramento.
- Considerar análise antimalware para uploads.

## Conversa com o chat

### 11. A pesquisa externa não pesquisa a internet

O serviço atual realiza outra chamada ao modelo com uma instrução para responder “como se estivesse pesquisando na internet”. Não existe uma ferramenta de busca real.

Arquivo relacionado:

- `app/Services/Search/WebSearchService.php`.

Impactos:

- Respostas podem ser inventadas.
- Não existem fontes ou links verificáveis.
- O comportamento comunicado ao usuário não corresponde ao que o sistema executa.

Ajustes necessários:

- Integrar uma ferramenta real de pesquisa.
- Registrar URLs, títulos, datas e trechos consultados.
- Fazer uma segunda etapa de síntese após a ferramenta retornar resultados.
- Mostrar as fontes utilizadas na interface.

### 12. Histórico enviado ao modelo cresce sem limite

Todas as mensagens da conversa são reenviadas ao provedor de IA em cada interação.

Arquivo relacionado:

- `app/Services/AI/PromptManager.php`.

Impactos:

- Aumento progressivo de custo e latência.
- Risco de exceder a janela de contexto.
- Possibilidade de falha completa em conversas longas.

Ajustes necessários:

- Enviar apenas uma janela das mensagens mais recentes.
- Criar um resumo acumulado da conversa.
- Estabelecer orçamento máximo de tokens.
- Paginar o histórico retornado ao front-end.

### 13. Texto digitado é perdido quando o envio falha

O componente limpa o campo imediatamente após emitir o evento, antes de a API confirmar o sucesso.

Arquivo relacionado:

- `resources/js/Components/Chat/ChatWindow.vue`.

Em uma falha de rede, banco ou OpenAI, o usuário precisa digitar novamente.

Ajustes necessários:

- Limpar o campo somente após a confirmação do envio.
- Criar uma mensagem local com estado `sending`.
- Exibir botão de nova tentativa quando houver falha.
- Preservar texto e anexo até a conclusão.

### 14. Falhas externas deixam mensagens parcialmente gravadas

A mensagem do usuário é persistida antes da conclusão do OCR, embedding e resposta do modelo. Não há tratamento específico dessas falhas no controller.

Arquivos relacionados:

- `app/AI/Agents/SupportAgent.php`.
- `app/Http/Controllers/Api/ChatController.php`.

Ajustes necessários:

- Adicionar estado à mensagem: `pending`, `completed` ou `failed`.
- Registrar uma resposta de erro recuperável.
- Implementar idempotência para evitar mensagens duplicadas em retries.
- Capturar exceções da OpenAI e retornar mensagens amigáveis sem detalhes internos.

### 15. Não há validação de imagem no front-end

O campo utiliza `accept="image/*"`, mas não verifica tamanho, tipo real ou dimensões antes do envio.

Arquivo relacionado:

- `resources/js/Components/Chat/ChatWindow.vue`.

Ajustes necessários:

- Limitar formatos aceitos, por exemplo PNG, JPEG e WebP.
- Validar o limite de tamanho antes do envio.
- Exibir mensagem clara de erro.
- Opcionalmente redimensionar imagens muito grandes antes do upload.

## Outros problemas encontrados

### Documentos exportados misturados com uploads

O endpoint de documentos lista todos os registros. A aba “Documentos Exportados” pode mostrar também arquivos enviados para a base de conhecimento.

Arquivo relacionado:

- `app/Http/Controllers/Api/DocumentController.php`.

Ajuste necessário: filtrar documentos exportados por `chat_id` ou adicionar um campo explícito de tipo.

### Download sempre força extensão PDF

O download acrescenta `.pdf` ao nome, mesmo que o registro represente TXT, DOC ou DOCX.

Arquivo relacionado:

- `app/Http/Controllers/Api/DocumentController.php`.

Ajuste necessário: armazenar e utilizar nome, extensão e MIME reais.

### Exposição de detalhes internos

O processamento de upload devolve ao cliente o texto da exceção capturada.

Arquivo relacionado:

- `app/Http/Controllers/Api/KnowledgeBaseController.php`.

Ajuste necessário: registrar detalhes somente no servidor e retornar um código de erro e mensagem genérica ao usuário.

### Possível sobrescrita de PDFs exportados

O nome do arquivo exportado é derivado do nome informado e do mês atual. Duas exportações com o mesmo nome podem utilizar o mesmo caminho.

Arquivo relacionado:

- `app/Services/ChatToPdfExporter.php`.

Ajuste necessário: adicionar UUID ou identificador único ao nome armazenado.

### Conteúdo sem escape na geração do PDF

Título e mensagens são inseridos diretamente no HTML usado pelo DomPDF.

Arquivo relacionado:

- `app/Services/ChatToPdfExporter.php`.

Ajuste necessário: escapar o conteúdo antes de gerar o HTML.

## Testes e qualidade

### Situação atual

- Os 12 testes passam quando executados dentro do container.
- A execução no host falha porque diretórios de teste pertencem a `nobody:nogroup`.
- O build do front-end passa.
- A sintaxe PHP passa.
- O Laravel Pint identifica problemas de estilo em controllers, serviços, requests, migrations e testes.

### Cobertura ausente

Não existem testes suficientes para:

- Envio de mensagem comum.
- Envio somente de imagem.
- OCR vazio ou com erro.
- Autorização de pesquisa externa.
- Recusa de pesquisa externa.
- Falha da OpenAI.
- Proteção de chats e documentos de outros usuários.
- Exclusão completa de documentos e chunks.
- Processamento real de DOCX.
- Processamento de PDF válido.
- OCR de PDF escaneado.
- Retry e idempotência.
- Conversas extensas e limite de contexto.

## Plano recomendado de implementação

### Prioridade 0 — Correções imediatas

1. Corrigir autorização e recusa da pesquisa externa.
2. Impedir embedding de texto vazio.
3. Adicionar autenticação, policies e escopo por usuário.
4. Adicionar rate limiting às rotas que consomem OpenAI.
5. Migrar uploads para armazenamento privado.

### Prioridade 1 — Documentos

1. Fazer `Document` representar o arquivo original e `KnowledgeBase` representar somente seus chunks.
2. Listar e excluir documentos completos.
3. Criar status de processamento.
4. Mover OCR e embeddings para filas.
5. Harmonizar limites de upload.
6. Remover suporte a DOC ou implementar conversão real.
7. Adicionar OCR para PDFs escaneados.

### Prioridade 2 — Chat

1. Preservar texto e anexos em caso de falha.
2. Implementar mensagens com estado e retry.
3. Limitar o histórico enviado ao modelo.
4. Implementar pesquisa externa real e apresentar fontes.
5. Persistir imagens em armazenamento privado.
6. Adicionar idempotência nos envios.

### Prioridade 3 — Qualidade e operação

1. Corrigir permissões de `storage` entre host e container.
2. Aplicar Laravel Pint.
3. Ampliar a suíte de testes.
4. Adicionar monitoramento de latência, custos, falhas de OCR e falhas da OpenAI.
5. Documentar limites, formatos e estados do processamento.

## Critérios sugeridos de conclusão

O fluxo pode ser considerado confiável quando:

- Consentir ou recusar uma busca nunca gerar mensagem ou embedding vazio.
- Nenhum usuário conseguir acessar chats ou arquivos de outro usuário.
- Documentos não ficarem publicamente acessíveis.
- Cada arquivo aparecer somente uma vez na interface.
- A exclusão de um documento remover arquivo e todos os chunks.
- PDFs escaneados mostrarem estado de OCR e indexação.
- Falhas de OpenAI preservarem a mensagem e permitirem nova tentativa.
- A pesquisa externa apresentar fontes verificáveis.
- Uploads grandes forem processados em fila e não bloquearem a requisição.
- Testes de chat, upload, autorização e falhas externas estiverem automatizados.
