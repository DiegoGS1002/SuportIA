<?php

namespace App\Providers;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\AI\PromptTemplates\SupportPrompt;
use App\AI\Tools\SefazCheckerTool;
use App\AI\Tools\WebSearchTool;
use App\Services\AI\AiServiceInterface;
use App\Services\AI\OpenIAService;
use App\Services\AI\PromptManager;
use App\Services\Search\VectorSearchService;
use App\Services\Search\WebSearchService;
use App\Services\Vision\OCRService;
use Illuminate\Support\ServiceProvider;
use OpenAI\Client;
use OpenAI\Factory;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function (): Client {
            return (new Factory)
                ->withApiKey((string) config('services.openai.api_key'))
                ->withOrganization(config('services.openai.organization'))
                ->make();
        });

        $this->app->bind(AiServiceInterface::class, function ($app): OpenIAService {
            return new OpenIAService(
                client: $app->make(Client::class),
                model: (string) config('services.openai.chat_model'),
            );
        });

        $this->app->singleton(OpenAIEmbeddingClient::class, function ($app): OpenAIEmbeddingClient {
            return new OpenAIEmbeddingClient(
                client: $app->make(Client::class),
                model: (string) config('services.openai.embeddings_model'),
                dimensions: (int) config('services.openai.embeddings_dimensions'),
            );
        });

        $this->app->singleton(WebSearchService::class, function ($app): WebSearchService {
            return new WebSearchService(
                client: $app->make(Client::class),
                model: (string) config('services.openai.chat_model'),
            );
        });

        $this->app->singleton(OCRService::class, function ($app): OCRService {
            return new OCRService(
                client: $app->make(Client::class),
                model: (string) config('services.openai.vision_model'),
            );
        });

        $this->app->singleton(SupportPrompt::class, function (): SupportPrompt {
            return new SupportPrompt(
                agentPromptPath: base_path('prompt/AgenteIA.md'),
            );
        });

        $this->app->singleton(VectorSearchService::class, function ($app): VectorSearchService {
            return new VectorSearchService(
                embeddingClient: $app->make(OpenAIEmbeddingClient::class),
            );
        });

        $this->app->singleton(PromptManager::class);
        $this->app->singleton(WebSearchTool::class);
        $this->app->singleton(SefazCheckerTool::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

