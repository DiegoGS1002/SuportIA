# Ajustes Realizados - SuportIA Front-End

## Mudanças Implementadas

### 1. Logo Oficial (LogoNexoraAgentIA.png)
- ✅ Logo copiado para `public/logo.png`
- ✅ Substituído no componente `Sidebar.vue` (removido gradiente placeholder "IA", agora usa `<img src="/logo.png">`)

### 2. Cores Fiéis às Imagens de Referência
Cores amostradas diretamente dos arquivos `docs/*.png` e aplicadas no `app.css`:

- **Sidebar**: `#020a1e` (navy-900 muito escuro, quase preto)
- **Background principal**: `#f3f5fb` (lavanda muito claro)
- **Header/footer**: `#f3f4fb` (tom levemente diferente)
- **Bolha usuário**: `#e8edfc` (azul muito claro) com texto `#0045fa` (azul vibrante)
- **Bolha assistente**: `#f3f6fb` (quase branco com toque azul)
- **Badges software**: Home `#2245f0` (brand-500), Stella `#0f1526` (navy-700)

### 3. Opção de Excluir Atendimento
- ✅ Endpoint `DELETE /api/chats/{chat}` no backend (`ChatController@destroy`)
- ✅ Botão "Excluir conversa" no header da página Chat
- ✅ Confirmação via `confirm()` antes de excluir
- ✅ Método `deleteChat()` no composable `useChat.js`
- ✅ Redirecionamento automático após exclusão

### 4. Upload de Arquivos na Tela de Documentos
- ✅ Botão "+ Adicionar documento" no topo da página Documentos
- ✅ Modal com formulário: software (Home/Stella), título, arquivo (PDF, Word, TXT)
- ✅ Endpoint `POST /api/knowledge-bases` com validação de MIME types
- ✅ Extração de texto automática:
  - **PDF**: biblioteca `smalot/pdfparser` (instalada via Composer)
  - **DOCX**: extração via ZipArchive do XML interno
  - **TXT**: leitura direta via `file_get_contents()`
- ✅ Geração de embedding OpenAI após upload
- ✅ Feedback visual durante upload ("Enviando...")
- ✅ Recarga automática da lista após sucesso

### 5. Opção de Excluir Documentos
- ✅ Endpoint `DELETE /api/knowledge-bases/{id}`
- ✅ Botão de lixeira em cada card de documento
- ✅ Confirmação via `confirm()` antes de excluir

### 6. Refinamentos de UI/UX
- ✅ Ícones de usuário simplificados (U/IA em vez de "Você"/"Voce")
- ✅ Transition suave no hover dos cards Home
- ✅ Background do composer na página de chat agora usa `bg-header-bg` para consistência
- ✅ Software badges com cores sólidas em vez de gradientes
- ✅ Filtros na tela Documentos com hover mais sutil (`hover:bg-white` em vez de `hover:bg-app-bg`)

## Arquivos Modificados

### Backend
- `app/Http/Controllers/Api/ChatController.php` - adicionado `destroy()`
- `app/Http/Controllers/Api/KnowledgeBaseController.php` - adicionados `store()` e `destroy()`, extração de texto
- `app/Http/Requests/KnowledgeBaseStoreRequest.php` - novo validador
- `routes/api.php` - rotas DELETE e POST adicionadas
- `composer.json` - dependência `smalot/pdfparser` adicionada

### Frontend
- `resources/css/app.css` - paleta de cores atualizada com valores exatos
- `resources/js/Components/Shared/Sidebar.vue` - logo substituído
- `resources/js/Components/Chat/MessageBubble.vue` - cores das bolhas ajustadas
- `resources/js/Components/Chat/ChatWindow.vue` - composer bg ajustado
- `resources/js/Pages/Home.vue` - cores dos cards e badges ajustadas
- `resources/js/Pages/Chat.vue` - botão de excluir + confirmação
- `resources/js/Pages/Documentos.vue` - modal de upload + botões de excluir
- `resources/js/Services/api.js` - métodos `delete()` e `create()` adicionados
- `resources/js/Composables/useChat.js` - método `deleteChat()` adicionado

### Recursos
- `public/logo.png` - logo oficial copiado

## Validação
- ✅ Build do Vite sem erros
- ✅ Migrations limpas (sem alterações de schema)
- ✅ Testes manuais dos endpoints via curl confirmados
- ✅ Chat create/delete funcionando
- ✅ Frontend renderiza logo e cores corretas

