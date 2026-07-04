<?php

namespace Database\Seeders;

use App\Models\Node;
use App\Models\NodeCategory;
use Illuminate\Database\Seeder;

class NodeSeeder extends Seeder
{
    /** @var list<string> */
    private const DISABLED_NODES = [];

    public function run(): void
    {
        $categories = NodeCategory::query()->pluck('id', 'slug');

        $nodes = [
            // ── Triggers & Events ─────────────────────────────────────────
            [
                'category' => 'triggers-events',
                'type' => 'trigger.webhook',
                'name' => 'Webhook',
                'description' => 'Starts the workflow when an HTTP request is received.',
                'icon' => 'bolt',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'http_method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'DELETE'], 'default' => 'POST'],
                        'path' => ['type' => 'string', 'description' => 'Custom webhook path'],
                        'authentication' => ['type' => 'string', 'enum' => ['none', 'header', 'basic'], 'default' => 'none'],
                    ],
                    'required' => ['http_method'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'headers' => ['type' => 'object'],
                        'body' => ['type' => 'object'],
                        'query' => ['type' => 'object'],
                    ],
                ],
            ],
            [
                'category' => 'triggers-events',
                'type' => 'trigger.schedule',
                'name' => 'Schedule',
                'description' => 'Starts the workflow on a recurring cron schedule.',
                'icon' => 'clock',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'cron' => ['type' => 'string', 'description' => 'Cron expression (e.g. */5 * * * *)'],
                        'timezone' => ['type' => 'string', 'default' => 'UTC'],
                    ],
                    'required' => ['cron'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'triggered_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
            ],
            [
                'category' => 'triggers-events',
                'type' => 'trigger.manual',
                'name' => 'Manual Trigger',
                'description' => 'Starts the workflow manually by the user.',
                'icon' => 'hand-raised',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'input_fields' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'enum' => ['string', 'number', 'boolean']],
                                ],
                            ],
                        ],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'object'],
                    ],
                ],
            ],

            // ── AI & Automation ───────────────────────────────────────────
            [
                'category' => 'ai-automation',
                'type' => 'ai.llm',
                'name' => 'LLM Prompt',
                'description' => 'Send a prompt to a large language model and receive a completion.',
                'icon' => 'cpu-chip',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0020,
                'latency_hint_ms' => 3000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                        'system_prompt' => ['type' => 'string'],
                        'temperature' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2, 'default' => 0.7],
                        'max_tokens' => ['type' => 'integer', 'default' => 1024],
                    ],
                    'required' => ['model'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'usage' => ['type' => 'object', 'properties' => [
                            'prompt_tokens' => ['type' => 'integer'],
                            'completion_tokens' => ['type' => 'integer'],
                        ]],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.text_classifier',
                'name' => 'Text Classifier',
                'description' => 'Classify text into predefined categories using AI.',
                'icon' => 'tag',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0010,
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of classification labels'],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                    'required' => ['categories'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.summarizer',
                'name' => 'Summarizer',
                'description' => 'Summarize long text into concise bullet points or a paragraph.',
                'icon' => 'document-text',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0015,
                'latency_hint_ms' => 2500,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'format' => ['type' => 'string', 'enum' => ['paragraph', 'bullets'], 'default' => 'paragraph'],
                        'max_length' => ['type' => 'integer', 'default' => 200],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.sentiment',
                'name' => 'Sentiment Analysis',
                'description' => 'Analyze sentiment — returns positive/negative/neutral with a confidence score and detected emotions.',
                'icon' => 'heart',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0010,
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'sentiment' => ['type' => 'string'],
                        'score' => ['type' => 'number'],
                        'emotions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.embeddings',
                'name' => 'Embeddings',
                'description' => 'Generate vector embeddings for text — useful for semantic search, clustering, and RAG pipelines.',
                'icon' => 'cube-transparent',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0002,
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'gemini', 'cohere', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'text-embedding-3-small'],
                        'dimensions' => ['type' => 'integer', 'default' => 1536],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'embeddings' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.image_generation',
                'name' => 'Image Generation',
                'description' => 'Generate images from text prompts using AI (DALL·E, Gemini, etc.).',
                'icon' => 'photo',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0400,
                'latency_hint_ms' => 10000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'gemini', 'xai'], 'default' => 'gemini'],
                        'model' => ['type' => 'string'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'images' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'image_count' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'ai.agent',
                'name' => 'AI Agent',
                'description' => 'Autonomous AI agent that decides which tools to use to complete a task.',
                'icon' => 'sparkles',
                'color' => '#7C3AED',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0500,
                'latency_hint_ms' => 15000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o'],
                        'system_prompt' => ['type' => 'string'],
                        'max_steps' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 25],
                        'tools' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Node types the agent can call as tools'],
                        'temperature' => ['type' => 'number', 'default' => 0.7, 'minimum' => 0, 'maximum' => 2],
                    ],
                    'required' => ['system_prompt', 'tools'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'response' => ['type' => 'string'],
                        'provider' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'rag.document_loader',
                'name' => 'Document Loader',
                'description' => 'Load documents from text, URL, or files for RAG processing.',
                'icon' => 'document-text',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['load_text', 'load_url', 'load_file'], 'default' => 'load_text'],
                        'text' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'file_path' => ['type' => 'string'],
                        'document_id' => ['type' => 'string'],
                        'metadata' => ['type' => 'object'],
                    ],
                    'required' => ['operation'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'documents' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'rag.chunker',
                'name' => 'Text Chunker',
                'description' => 'Split documents into smaller chunks for embedding. Supports fixed-size and semantic chunking.',
                'icon' => 'scissors',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['chunk_fixed', 'chunk_semantic'], 'default' => 'chunk_fixed'],
                        'chunk_size' => ['type' => 'integer', 'default' => 1000],
                        'overlap' => ['type' => 'integer', 'default' => 200],
                        'max_chunk_size' => ['type' => 'integer', 'default' => 1000],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'documents' => ['type' => 'array'],
                    ],
                    'required' => ['documents'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'chunks' => ['type' => 'array'],
                        'total_chunks' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'rag.vector_store_writer',
                'name' => 'Vector Store Writer',
                'description' => 'Store document chunks as embeddings in the vector database for retrieval.',
                'icon' => 'archive-box',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['store', 'delete_document', 'delete_collection'], 'default' => 'store'],
                        'collection_name' => ['type' => 'string', 'default' => 'default'],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'text-embedding-ada-002'],
                        'document_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation', 'collection_name'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'chunks' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_name' => ['type' => 'string'],
                        'chunks_stored' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai-automation',
                'type' => 'rag.query',
                'name' => 'RAG Query',
                'description' => 'Query documents using Retrieval-Augmented Generation. Finds relevant context and generates AI-powered answers.',
                'icon' => 'magnifying-glass',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['query'], 'default' => 'query'],
                        'query' => ['type' => 'string'],
                        'collection_name' => ['type' => 'string', 'default' => 'default'],
                        'top_k' => ['type' => 'integer', 'default' => 5],
                        'min_similarity' => ['type' => 'number', 'default' => 0.7, 'minimum' => 0, 'maximum' => 1],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq'], 'default' => 'openai'],
                        'llm_model' => ['type' => 'string', 'default' => 'gpt-4o'],
                        'embedding_model' => ['type' => 'string', 'default' => 'text-embedding-ada-002'],
                        'system_prompt' => ['type' => 'string'],
                        'include_citations' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['operation', 'query', 'collection_name'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'answer' => ['type' => 'string'],
                        'sources' => ['type' => 'array'],
                        'query' => ['type' => 'string'],
                        'retrieved_chunks' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── Flow & Logic ──────────────────────────────────────────────
            [
                'category' => 'flow-logic',
                'type' => 'flow.if',
                'name' => 'If / Else',
                'description' => 'Branch the workflow based on a condition.',
                'icon' => 'arrows-right-left',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'conditions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string', 'enum' => ['equals', 'not_equals', 'contains', 'gt', 'lt', 'gte', 'lte', 'is_empty', 'is_not_empty']],
                                    'value' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'combine' => ['type' => 'string', 'enum' => ['and', 'or'], 'default' => 'and'],
                    ],
                    'required' => ['conditions'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'branch' => ['type' => 'string', 'enum' => ['true', 'false']],
                    ],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.switch',
                'name' => 'Switch',
                'description' => 'Multi-way branching. Route to different paths based on value matching.',
                'icon' => 'arrows-pointing-out',
                'color' => '#A855F7',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['description' => 'Value to match against cases'],
                        'cases' => ['type' => 'object', 'description' => 'Cases mapping (value => route)'],
                        'default_route' => ['type' => 'string', 'default' => 'default'],
                        'mode' => ['type' => 'string', 'enum' => ['exact', 'loose', 'regex', 'range', 'type', 'contains'], 'default' => 'exact'],
                    ],
                    'required' => ['value', 'cases'],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.loop',
                'name' => 'Loop',
                'description' => 'Advanced iterator with serial, parallel, and batched execution modes.',
                'icon' => 'arrow-path',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'source' => ['type' => 'string', 'default' => 'items'],
                        'mode' => ['type' => 'string', 'enum' => ['serial', 'parallel', 'batched'], 'default' => 'serial'],
                        'batch_size' => ['type' => 'integer', 'default' => 10, 'minimum' => 1],
                        'max_concurrency' => ['type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 50],
                        'max_iterations' => ['type' => 'integer', 'default' => null, 'minimum' => 1],
                        'delay_ms' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                        'on_error' => ['type' => 'string', 'enum' => ['stop', 'continue', 'fail_after_n'], 'default' => 'stop'],
                        'fail_after_errors' => ['type' => 'integer', 'default' => 3, 'minimum' => 1],
                        'break_condition' => ['type' => 'string', 'default' => null],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'item_count' => ['type' => 'integer'],
                        'mode' => ['type' => 'string'],
                        'batch_size' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.delay',
                'name' => 'Delay',
                'description' => 'Pause the workflow for a specified amount of time.',
                'icon' => 'clock',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'duration' => ['type' => 'integer', 'description' => 'Duration value'],
                        'unit' => ['type' => 'string', 'enum' => ['seconds', 'minutes', 'hours'], 'default' => 'seconds'],
                    ],
                    'required' => ['duration'],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.merge',
                'name' => 'Merge',
                'description' => 'Merge multiple branches back into a single path.',
                'icon' => 'arrows-pointing-in',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['wait_all', 'first'], 'default' => 'wait_all'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'inputs' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'merged' => ['type' => 'object'],
                    ],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.try_catch',
                'name' => 'Try / Catch',
                'description' => 'Wrap nodes to catch and handle errors gracefully.',
                'icon' => 'shield-check',
                'color' => '#EF4444',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'catch_errors' => ['type' => 'boolean', 'default' => true],
                        'on_error' => ['type' => 'string', 'enum' => ['continue', 'stop', 'retry'], 'default' => 'continue'],
                        'retry_count' => ['type' => 'integer', 'default' => 0],
                        'retry_delay_ms' => ['type' => 'integer', 'default' => 1000],
                        'log_errors' => ['type' => 'boolean', 'default' => true],
                        'fallback_value' => ['description' => 'Value to return on error'],
                    ],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.wait_for_event',
                'name' => 'Wait for Event',
                'description' => 'Pause workflow until an external event is received (webhook, signal, timeout).',
                'icon' => 'pause-circle',
                'color' => '#EC4899',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_type' => ['type' => 'string', 'enum' => ['webhook', 'signal', 'timeout'], 'default' => 'webhook'],
                        'event_id' => ['type' => 'string'],
                        'timeout_seconds' => ['type' => 'integer', 'default' => 3600],
                        'timeout_action' => ['type' => 'string', 'enum' => ['fail', 'continue', 'retry'], 'default' => 'fail'],
                    ],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.batch_processor',
                'name' => 'Batch Processor',
                'description' => 'Process items in configurable batches with commits and error handling.',
                'icon' => 'queue-list',
                'color' => '#14B8A6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'batch_size' => ['type' => 'integer', 'default' => 100, 'minimum' => 1],
                        'pause_ms' => ['type' => 'integer', 'default' => 0],
                        'commit_each_batch' => ['type' => 'boolean', 'default' => true],
                        'stop_on_error' => ['type' => 'boolean', 'default' => false],
                        'max_retries' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
            ],
            [
                'category' => 'flow-logic',
                'type' => 'flow.retry',
                'name' => 'Retry',
                'description' => 'Retry failed operations with exponential backoff, linear, or fixed delay strategies.',
                'icon' => 'arrow-path',
                'color' => '#EF4444',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'max_attempts' => ['type' => 'integer', 'default' => 3, 'minimum' => 1],
                        'initial_delay_ms' => ['type' => 'integer', 'default' => 1000],
                        'max_delay_ms' => ['type' => 'integer', 'default' => 60000],
                        'backoff_strategy' => ['type' => 'string', 'enum' => ['exponential', 'linear', 'fixed'], 'default' => 'exponential'],
                        'backoff_multiplier' => ['type' => 'number', 'default' => 2.0],
                        'jitter' => ['type' => 'boolean', 'default' => true],
                        'retry_on_errors' => ['type' => 'array', 'default' => ['all']],
                        'abort_on_errors' => ['type' => 'array', 'default' => []],
                    ],
                ],
            ],

            // ── Data & Transform ──────────────────────────────────────────
            [
                'category' => 'data-transform',
                'type' => 'data.transform',
                'name' => 'Data Transform',
                'description' => 'Map, rename, or restructure data fields.',
                'icon' => 'adjustments-horizontal',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mappings' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'source' => ['type' => 'string'],
                                    'target' => ['type' => 'string'],
                                    'transform' => ['type' => 'string', 'enum' => ['none', 'uppercase', 'lowercase', 'trim', 'to_number', 'to_string', 'to_boolean']],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['mappings'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                    'required' => ['data'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.filter',
                'name' => 'Filter',
                'description' => 'Filter arrays by conditions, values, remove duplicates, or empty items.',
                'icon' => 'funnel',
                'color' => '#F59E0B',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['filter_by_condition', 'filter_by_value', 'remove_duplicates', 'remove_empty'],
                            'default' => 'filter_by_condition',
                        ],
                        'field' => ['type' => 'string'],
                        'operator' => ['type' => 'string', 'enum' => ['equals', 'not_equals', 'contains', 'gt', 'lt', 'gte', 'lte', 'regex', 'in', 'is_empty', 'is_not_empty'], 'default' => 'equals'],
                        'value' => ['description' => 'Value to compare against'],
                        'mode' => ['type' => 'string', 'enum' => ['keep', 'remove'], 'default' => 'keep'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.aggregate',
                'name' => 'Aggregate',
                'description' => 'Aggregate array items (count, sum, average, min, max).',
                'icon' => 'calculator',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['count', 'sum', 'average', 'min', 'max']],
                        'field' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => ['type' => 'number'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.set_variable',
                'name' => 'Set Variable',
                'description' => 'Set a workflow-level variable for use in subsequent nodes.',
                'icon' => 'variable',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variable_name' => ['type' => 'string'],
                        'value' => ['type' => 'string', 'description' => 'Static value or expression'],
                    ],
                    'required' => ['variable_name', 'value'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variable_name' => ['type' => 'string'],
                        'value' => [],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.json',
                'name' => 'JSON',
                'description' => 'Parse, stringify, extract, merge, and validate JSON data.',
                'icon' => 'code-bracket',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['parse', 'stringify', 'extract', 'merge', 'validate'], 'default' => 'parse'],
                        'json_string' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                        'path' => ['type' => 'string', 'description' => 'JSON path for extraction (dot notation)'],
                        'pretty_print' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.array',
                'name' => 'Array Operations',
                'description' => 'Map, reduce, sort, group, flatten, and transform arrays.',
                'icon' => 'queue-list',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['map', 'reduce', 'sort', 'group_by', 'unique', 'flatten', 'slice', 'chunk'], 'default' => 'map'],
                        'field' => ['type' => 'string'],
                        'fields' => ['type' => 'object'],
                        'direction' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.string',
                'name' => 'String Operations',
                'description' => 'Concat, split, replace, regex, case conversion, trim, and template rendering.',
                'icon' => 'document-text',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['concat', 'split', 'replace', 'regex', 'case', 'trim', 'substring', 'template', 'length'], 'default' => 'concat'],
                        'string' => ['type' => 'string'],
                        'strings' => ['type' => 'array'],
                        'separator' => ['type' => 'string'],
                        'case' => ['type' => 'string', 'enum' => ['lower', 'upper', 'title', 'camel', 'snake', 'kebab']],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.math',
                'name' => 'Math',
                'description' => 'Mathematical operations: calculate, aggregate, round, random, formulas.',
                'icon' => 'calculator',
                'color' => '#06B6D4',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['calculate', 'aggregate', 'round', 'random', 'formula'], 'default' => 'calculate'],
                        'a' => ['type' => 'number'],
                        'b' => ['type' => 'number'],
                        'numbers' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.datetime',
                'name' => 'Date / Time',
                'description' => 'Parse, format, add, subtract, compare dates and times. Timezone conversion.',
                'icon' => 'clock',
                'color' => '#F97316',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['parse', 'format', 'add', 'subtract', 'diff', 'compare', 'now', 'timezone'], 'default' => 'now'],
                        'date' => ['type' => 'string'],
                        'format' => ['type' => 'string'],
                        'timezone' => ['type' => 'string'],
                        'unit' => ['type' => 'string', 'enum' => ['years', 'months', 'days', 'hours', 'minutes', 'seconds']],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.variable',
                'name' => 'Variable',
                'description' => 'Store and retrieve variables across workflow executions. Supports workflow, execution, and workspace scopes.',
                'icon' => 'variable',
                'color' => '#F59E0B',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['set', 'get', 'increment', 'decrement', 'append', 'delete'], 'default' => 'set'],
                        'key' => ['type' => 'string'],
                        'value' => ['description' => 'Value to store'],
                        'scope' => ['type' => 'string', 'enum' => ['workflow', 'execution', 'workspace'], 'default' => 'workflow'],
                        'default' => ['description' => 'Default value for get operation'],
                        'ttl' => ['type' => 'integer'],
                    ],
                    'required' => ['operation', 'key'],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'data.cache',
                'name' => 'Cache',
                'description' => 'Cache data for performance optimization. Get, set, has, delete, remember operations.',
                'icon' => 'server-stack',
                'color' => '#06B6D4',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['get', 'set', 'has', 'delete', 'clear', 'remember'], 'default' => 'get'],
                        'key' => ['type' => 'string'],
                        'value' => ['description' => 'Value to cache'],
                        'ttl' => ['type' => 'integer'],
                        'prefix' => ['type' => 'string', 'default' => 'workflow'],
                        'default' => ['description' => 'Default value if key not found'],
                    ],
                    'required' => ['operation', 'key'],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'util.code',
                'name' => 'Code (JavaScript)',
                'description' => 'Execute custom JavaScript code to transform data.',
                'icon' => 'code-bracket',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string'],
                    ],
                    'required' => ['code'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => [],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'util.template',
                'name' => 'Text Template',
                'description' => 'Render a text template with dynamic variables.',
                'icon' => 'document-text',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'template' => ['type' => 'string', 'description' => 'Template with {{variable}} placeholders'],
                    ],
                    'required' => ['template'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variables' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'util.logger',
                'name' => 'Logger',
                'description' => 'Log messages and debug workflow execution. Supports multiple log levels and channels.',
                'icon' => 'document-magnifying-glass',
                'color' => '#6366F1',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['log', 'debug', 'inspect'], 'default' => 'log'],
                        'message' => ['type' => 'string'],
                        'level' => ['type' => 'string', 'enum' => ['debug', 'info', 'warning', 'error'], 'default' => 'info'],
                        'data' => ['type' => 'object'],
                        'channel' => ['type' => 'string', 'default' => 'workflow'],
                        'include_context' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'util.error_handler',
                'name' => 'Error Handler',
                'description' => 'Catch and handle errors from upstream nodes.',
                'icon' => 'exclamation-triangle',
                'color' => '#6B7280',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'on_error' => ['type' => 'string', 'enum' => ['stop', 'continue', 'retry'], 'default' => 'stop'],
                        'max_retries' => ['type' => 'integer', 'default' => 3],
                        'retry_delay_seconds' => ['type' => 'integer', 'default' => 5],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'storage.read_file',
                'name' => 'Read File',
                'description' => 'Read the contents of a file from local or cloud storage.',
                'icon' => 'document-arrow-down',
                'color' => '#0EA5E9',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'disk' => ['type' => 'string', 'enum' => ['local', 's3', 'gcs'], 'default' => 'local'],
                        'path' => ['type' => 'string'],
                        'encoding' => ['type' => 'string', 'enum' => ['utf-8', 'base64'], 'default' => 'utf-8'],
                    ],
                    'required' => ['path'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                        'mime_type' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'storage.write_file',
                'name' => 'Write File',
                'description' => 'Write content to a file in local or cloud storage.',
                'icon' => 'document-arrow-up',
                'color' => '#0EA5E9',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'disk' => ['type' => 'string', 'enum' => ['local', 's3', 'gcs'], 'default' => 'local'],
                        'path' => ['type' => 'string'],
                    ],
                    'required' => ['path'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                    ],
                    'required' => ['content'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'data-transform',
                'type' => 'comm.send_email',
                'name' => 'Send Email',
                'description' => 'Send an email using SMTP or a transactional email provider.',
                'icon' => 'envelope',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'credential_type' => 'smtp',
                'latency_hint_ms' => 1500,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'body_type' => ['type' => 'string', 'enum' => ['text', 'html'], 'default' => 'html'],
                        'from' => ['type' => 'string'],
                        'from_name' => ['type' => 'string'],
                        'cc' => ['type' => 'array'],
                        'bcc' => ['type' => 'array'],
                    ],
                    'required' => ['to', 'subject'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => ['type' => 'string'],
                    ],
                    'required' => ['body'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'string'],
                        'sent' => ['type' => 'boolean'],
                    ],
                ],
            ],

            // ── HTTP Request ──────────────────────────────────────────────
            [
                'category' => 'http-request',
                'type' => 'http.request',
                'name' => 'HTTP Request',
                'description' => 'Make an HTTP request to any URL.',
                'icon' => 'globe-alt',
                'color' => '#F97316',
                'node_kind' => 'action',
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], 'default' => 'GET'],
                        'url' => ['type' => 'string'],
                        'headers' => ['type' => 'object'],
                        'timeout' => ['type' => 'integer', 'default' => 30],
                        'response_type' => ['type' => 'string', 'enum' => ['json', 'text', 'binary'], 'default' => 'json'],
                    ],
                    'required' => ['method', 'url'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => [],
                        'query_params' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status_code' => ['type' => 'integer'],
                        'headers' => ['type' => 'object'],
                        'body' => [],
                    ],
                ],
            ],
            [
                'category' => 'http-request',
                'type' => 'http.graphql',
                'name' => 'GraphQL Request',
                'description' => 'Execute a GraphQL query or mutation.',
                'icon' => 'code-bracket-square',
                'color' => '#F97316',
                'node_kind' => 'action',
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'endpoint' => ['type' => 'string'],
                        'headers' => ['type' => 'object'],
                    ],
                    'required' => ['endpoint'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'variables' => ['type' => 'object'],
                    ],
                    'required' => ['query'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                        'errors' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'http-request',
                'type' => 'http.webhook_response',
                'name' => 'Webhook Response',
                'description' => 'Send a custom HTTP response back to the webhook caller.',
                'icon' => 'arrow-uturn-left',
                'color' => '#F97316',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status_code' => ['type' => 'integer', 'default' => 200],
                        'headers' => ['type' => 'object'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => [],
                    ],
                ],
            ],

            // ── Gmail ─────────────────────────────────────────────────────
            [
                'category' => 'gmail',
                'type' => 'gmail.send_email',
                'name' => 'Gmail',
                'description' => 'Send emails, reply, manage labels, and list messages via Gmail.',
                'icon' => 'envelope',
                'color' => '#EA4335',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_email', 'reply_to_message', 'get_message', 'list_messages', 'modify_message', 'add_label', 'list_labels', 'delete_message', 'create_draft'], 'default' => 'send_email'],
                        'to' => ['type' => 'string', 'description' => 'Recipient email address'],
                        'subject' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                        'is_html' => ['type' => 'boolean', 'default' => false],
                        'message_id' => ['type' => 'string', 'description' => 'Gmail message ID'],
                        'thread_id' => ['type' => 'string', 'description' => 'Thread ID for reply'],
                        'query' => ['type' => 'string', 'description' => 'Gmail search query e.g. from:user@example.com'],
                        'max_results' => ['type' => 'integer', 'default' => 10],
                        'label_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Label IDs to add'],
                        'add_label_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'remove_label_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['operation'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'threadId' => ['type' => 'string'],
                        'messages' => ['type' => 'array'],
                        'labels' => ['type' => 'array'],
                    ],
                ],
            ],

            // ── Google Sheets ─────────────────────────────────────────────
            [
                'category' => 'google-sheets',
                'type' => 'google_sheets.get_rows',
                'name' => 'Google Sheets',
                'description' => 'Read, append, update, delete, and lookup rows in Google Sheets.',
                'icon' => 'table-cells',
                'color' => '#34A853',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['get_rows', 'append_row', 'update_row', 'clear_range', 'delete_rows', 'lookup_rows', 'create_spreadsheet', 'get_spreadsheet_info'], 'default' => 'get_rows'],
                        'spreadsheet_id' => ['type' => 'string'],
                        'range' => ['type' => 'string', 'default' => 'Sheet1', 'description' => 'A1 notation e.g. Sheet1!A1:D10'],
                        'values' => ['type' => 'array', 'description' => 'Row values for append/update'],
                        'title' => ['type' => 'string', 'description' => 'Spreadsheet title (create_spreadsheet)'],
                        'lookup_column' => ['type' => 'string', 'default' => 'A', 'description' => 'Column letter to search in (lookup_rows)'],
                        'lookup_value' => ['type' => 'string', 'description' => 'Value to search for (lookup_rows)'],
                        'sheet_id' => ['type' => 'integer', 'default' => 0, 'description' => 'Sheet ID for delete_rows'],
                        'start_index' => ['type' => 'integer', 'description' => 'Start row index (0-based, delete_rows)'],
                        'end_index' => ['type' => 'integer', 'description' => 'End row index exclusive (delete_rows)'],
                    ],
                    'required' => ['operation', 'spreadsheet_id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'values' => ['type' => 'array'],
                        'rows' => ['type' => 'array'],
                        'count' => ['type' => 'integer'],
                        'updatedCells' => ['type' => 'integer'],
                        'spreadsheetId' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── Google Drive ──────────────────────────────────────────────
            [
                'category' => 'google-drive',
                'type' => 'google_drive.list_files',
                'name' => 'Google Drive',
                'description' => 'List, get, upload, download, update, share, or delete files in Google Drive.',
                'icon' => 'folder',
                'color' => '#1A73E8',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_files', 'get_file', 'download_file', 'upload_file', 'update_file', 'create_folder', 'delete_file', 'share_file'], 'default' => 'list_files'],
                        'file_id' => ['type' => 'string'],
                        'query' => ['type' => 'string', 'description' => 'Drive search query e.g. name contains "report"'],
                        'page_size' => ['type' => 'integer', 'default' => 10],
                        'name' => ['type' => 'string', 'description' => 'File or folder name'],
                        'content' => ['type' => 'string', 'description' => 'File content (upload_file)'],
                        'mime_type' => ['type' => 'string', 'default' => 'text/plain'],
                        'parent_id' => ['type' => 'string', 'description' => 'Parent folder ID'],
                        'type' => ['type' => 'string', 'enum' => ['user', 'group', 'domain', 'anyone'], 'default' => 'user', 'description' => 'Permission type (share_file)'],
                        'role' => ['type' => 'string', 'enum' => ['reader', 'commenter', 'writer'], 'default' => 'reader'],
                        'email' => ['type' => 'string', 'description' => 'Email to share with'],
                    ],
                    'required' => ['operation'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'files' => ['type' => 'array'],
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'deleted' => ['type' => 'boolean'],
                    ],
                ],
            ],

            // ── Google Calendar ───────────────────────────────────────────
            [
                'category' => 'google-calendar',
                'type' => 'google_calendar.list_events',
                'name' => 'Google Calendar',
                'description' => 'List, get, create, update, or delete calendar events.',
                'icon' => 'calendar',
                'color' => '#4285F4',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_events', 'get_event', 'create_event', 'update_event', 'delete_event', 'list_calendars'], 'default' => 'list_events'],
                        'calendar_id' => ['type' => 'string', 'default' => 'primary'],
                        'event_id' => ['type' => 'string'],
                        'title' => ['type' => 'string', 'description' => 'Event title'],
                        'description' => ['type' => 'string'],
                        'start' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Start datetime (RFC3339)'],
                        'end' => ['type' => 'string', 'format' => 'date-time', 'description' => 'End datetime (RFC3339)'],
                        'timezone' => ['type' => 'string', 'default' => 'UTC'],
                        'attendees' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of attendee emails'],
                        'time_min' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Lower bound for list_events'],
                        'max_results' => ['type' => 'integer', 'default' => 10],
                    ],
                    'required' => ['operation', 'calendar_id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                        'id' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'deleted' => ['type' => 'boolean'],
                    ],
                ],
            ],

            // ── Slack ─────────────────────────────────────────────────────
            [
                'category' => 'slack',
                'type' => 'slack.send_message',
                'name' => 'Slack',
                'description' => 'Send messages, manage channels, upload files, and list users in Slack.',
                'icon' => 'chat-bubble-left',
                'color' => '#4A154B',
                'node_kind' => 'action',
                'credential_type' => 'slack',
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_message', 'create_channel', 'invite_to_channel', 'get_channel_history', 'upload_file', 'list_channels', 'list_users'], 'default' => 'send_message'],
                        'channel' => ['type' => 'string', 'description' => 'Channel ID or name'],
                        'text' => ['type' => 'string', 'description' => 'Message text (send_message)'],
                        'username' => ['type' => 'string', 'description' => 'Bot display name override'],
                        'icon_emoji' => ['type' => 'string', 'description' => 'Emoji icon override e.g. :robot_face:'],
                        'name' => ['type' => 'string', 'description' => 'Channel name (create_channel)'],
                        'is_private' => ['type' => 'boolean', 'default' => false],
                        'users' => ['type' => 'string', 'description' => 'Comma-separated user IDs (invite_to_channel)'],
                        'limit' => ['type' => 'integer', 'default' => 100],
                        'exclude_archived' => ['type' => 'boolean', 'default' => true],
                        'content' => ['type' => 'string', 'description' => 'File content (upload_file)'],
                        'filename' => ['type' => 'string', 'default' => 'file.txt'],
                    ],
                    'required' => ['operation'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ts' => ['type' => 'string'],
                        'channel' => ['type' => 'string'],
                        'channels' => ['type' => 'array'],
                        'members' => ['type' => 'array'],
                        'messages' => ['type' => 'array'],
                    ],
                ],
            ],

            // ── Discord ───────────────────────────────────────────────────
            [
                'category' => 'discord',
                'type' => 'comm.discord_message',
                'name' => 'Discord',
                'description' => 'Send a message to a Discord channel via webhook.',
                'icon' => 'chat-bubble-bottom-center-text',
                'color' => '#5865F2',
                'node_kind' => 'action',
                'credential_type' => 'discord',
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'webhook_url' => ['type' => 'string'],
                        'username' => ['type' => 'string'],
                    ],
                    'required' => ['webhook_url'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                    ],
                    'required' => ['content'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── Telegram ──────────────────────────────────────────────────
            [
                'category' => 'telegram',
                'type' => 'telegram.send_message',
                'name' => 'Telegram',
                'description' => 'Send messages, photos, and documents via Telegram Bot API.',
                'icon' => 'paper-airplane',
                'color' => '#0088CC',
                'node_kind' => 'action',
                'credential_type' => 'telegram',
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_message', 'send_photo', 'send_document', 'get_updates', 'edit_message', 'delete_message'], 'default' => 'send_message'],
                        'chat_id' => ['type' => 'string'],
                        'parse_mode' => ['type' => 'string', 'enum' => ['HTML', 'Markdown', 'MarkdownV2'], 'default' => 'HTML'],
                    ],
                    'required' => ['operation', 'chat_id'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'integer'],
                        'sent' => ['type' => 'boolean'],
                    ],
                ],
            ],

            // ── GitHub ────────────────────────────────────────────────────
            [
                'category' => 'github',
                'type' => 'github.list_repos',
                'name' => 'GitHub',
                'description' => 'List repos, create issues, manage pull requests on GitHub.',
                'icon' => 'code-bracket',
                'color' => '#181717',
                'node_kind' => 'action',
                'credential_type' => 'github',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_repos', 'create_issue', 'list_issues', 'create_pull_request', 'list_pull_requests', 'get_repo'], 'default' => 'list_repos'],
                        'owner' => ['type' => 'string'],
                        'repo' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── GitLab ────────────────────────────────────────────────────
            [
                'category' => 'gitlab',
                'type' => 'gitlab.list_issues',
                'name' => 'GitLab',
                'description' => 'Manage projects, issues, merge requests, and pipelines in GitLab.',
                'icon' => 'code-bracket',
                'color' => '#FC6D26',
                'node_kind' => 'action',
                'credential_type' => 'gitlab',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_issues', 'create_issue', 'update_issue', 'list_merge_requests', 'create_merge_request', 'trigger_pipeline', 'list_pipelines'], 'default' => 'list_issues'],
                        'project_id' => ['type' => 'string'],
                        'state' => ['type' => 'string', 'enum' => ['opened', 'closed', 'all'], 'default' => 'opened'],
                    ],
                    'required' => ['operation', 'project_id'],
                ],
            ],

            // ── Jira ──────────────────────────────────────────────────────
            [
                'category' => 'jira',
                'type' => 'jira.create_issue',
                'name' => 'Jira',
                'description' => 'Create, update, and query Jira issues and projects.',
                'icon' => 'ticket',
                'color' => '#0052CC',
                'node_kind' => 'action',
                'credential_type' => 'jira',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_issue', 'update_issue', 'get_issue', 'list_issues', 'transition_issue', 'add_comment'], 'default' => 'create_issue'],
                        'project_key' => ['type' => 'string'],
                        'issue_type' => ['type' => 'string', 'enum' => ['Bug', 'Task', 'Story', 'Epic'], 'default' => 'Task'],
                        'title' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Linear ────────────────────────────────────────────────────
            [
                'category' => 'linear',
                'type' => 'linear.create_issue',
                'name' => 'Linear',
                'description' => 'Create and manage issues, projects, and cycles in Linear.',
                'icon' => 'document-check',
                'color' => '#5E6AD2',
                'node_kind' => 'action',
                'credential_type' => 'linear',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_issue', 'update_issue', 'list_issues', 'list_teams', 'list_projects', 'get_issue'], 'default' => 'create_issue'],
                        'team_id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'priority' => ['type' => 'integer', 'enum' => [0, 1, 2, 3, 4], 'default' => 0, 'description' => '0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Notion ────────────────────────────────────────────────────
            [
                'category' => 'notion',
                'type' => 'notion.query_database',
                'name' => 'Notion',
                'description' => 'Create pages, query databases, or update content in Notion.',
                'icon' => 'document',
                'color' => '#191919',
                'node_kind' => 'action',
                'credential_type' => 'notion',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['query_database', 'create_page', 'update_page', 'get_page', 'append_block', 'list_databases'], 'default' => 'query_database'],
                        'database_id' => ['type' => 'string'],
                        'page_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Airtable ──────────────────────────────────────────────────
            [
                'category' => 'airtable',
                'type' => 'airtable.list_records',
                'name' => 'Airtable',
                'description' => 'List, create, update, or delete records in Airtable bases.',
                'icon' => 'table-cells',
                'color' => '#18BFFF',
                'node_kind' => 'action',
                'credential_type' => 'airtable',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_records', 'get_record', 'create_record', 'update_record', 'delete_record'], 'default' => 'list_records'],
                        'base_id' => ['type' => 'string'],
                        'table_name' => ['type' => 'string'],
                        'record_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation', 'base_id', 'table_name'],
                ],
            ],

            // ── Trello ────────────────────────────────────────────────────
            [
                'category' => 'trello',
                'type' => 'trello.create_card',
                'name' => 'Trello',
                'description' => 'Create cards, manage boards, lists, and members in Trello.',
                'icon' => 'rectangle-stack',
                'color' => '#0052CC',
                'node_kind' => 'action',
                'credential_type' => 'trello',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_card', 'update_card', 'get_card', 'list_cards', 'delete_card', 'move_card', 'list_boards', 'list_lists'], 'default' => 'create_card'],
                        'list_id' => ['type' => 'string'],
                        'board_id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── HubSpot ───────────────────────────────────────────────────
            [
                'category' => 'hubspot',
                'type' => 'hubspot.create_contact',
                'name' => 'HubSpot',
                'description' => 'Manage contacts, companies, deals, and pipelines in HubSpot CRM.',
                'icon' => 'user-group',
                'color' => '#FF7A59',
                'node_kind' => 'action',
                'credential_type' => 'hubspot',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_contact', 'update_contact', 'get_contact', 'list_contacts', 'create_deal', 'update_deal', 'list_deals', 'create_company', 'list_companies'], 'default' => 'create_contact'],
                        'email' => ['type' => 'string'],
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Salesforce ────────────────────────────────────────────────
            [
                'category' => 'salesforce',
                'type' => 'salesforce.query',
                'name' => 'Salesforce',
                'description' => 'Query, create, update, and delete Salesforce CRM records using SOQL.',
                'icon' => 'cloud',
                'color' => '#00A1E0',
                'node_kind' => 'action',
                'credential_type' => 'salesforce',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['query', 'create', 'update', 'delete', 'upsert', 'describe'], 'default' => 'query'],
                        'object' => ['type' => 'string', 'description' => 'Salesforce object type (e.g. Lead, Contact, Opportunity)'],
                        'soql' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Stripe ────────────────────────────────────────────────────
            [
                'category' => 'stripe',
                'type' => 'stripe.create_customer',
                'name' => 'Stripe',
                'description' => 'Create customers, invoices, charges, and manage payments via Stripe.',
                'icon' => 'credit-card',
                'color' => '#635BFF',
                'node_kind' => 'action',
                'credential_type' => 'stripe',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_customer', 'create_invoice', 'list_payments', 'create_charge', 'get_balance', 'create_subscription', 'cancel_subscription'], 'default' => 'create_customer'],
                        'email' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'amount' => ['type' => 'integer', 'description' => 'Amount in smallest currency unit (e.g. cents)'],
                        'currency' => ['type' => 'string', 'default' => 'usd'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Mailchimp ─────────────────────────────────────────────────
            [
                'category' => 'mailchimp',
                'type' => 'mailchimp.add_subscriber',
                'name' => 'Mailchimp',
                'description' => 'Manage subscribers, lists, campaigns, and automations in Mailchimp.',
                'icon' => 'envelope-open',
                'color' => '#FFE01B',
                'node_kind' => 'action',
                'credential_type' => 'mailchimp',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['add_subscriber', 'update_subscriber', 'get_subscriber', 'remove_subscriber', 'list_campaigns', 'list_lists'], 'default' => 'add_subscriber'],
                        'list_id' => ['type' => 'string'],
                        'email_address' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['subscribed', 'unsubscribed', 'pending'], 'default' => 'subscribed'],
                    ],
                    'required' => ['operation', 'list_id'],
                ],
            ],

            // ── SendGrid ──────────────────────────────────────────────────
            [
                'category' => 'sendgrid',
                'type' => 'sendgrid.send_email',
                'name' => 'SendGrid',
                'description' => 'Send transactional and marketing emails via SendGrid API.',
                'icon' => 'envelope',
                'color' => '#1A82E2',
                'node_kind' => 'action',
                'credential_type' => 'sendgrid',
                'latency_hint_ms' => 1500,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_email', 'send_template', 'add_to_list', 'list_contacts'], 'default' => 'send_email'],
                        'to' => ['type' => 'string'],
                        'from' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'template_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => ['type' => 'string'],
                        'template_data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'string'],
                        'sent' => ['type' => 'boolean'],
                    ],
                ],
            ],

            // ── Twilio ────────────────────────────────────────────────────
            [
                'category' => 'twilio',
                'type' => 'twilio.send_sms',
                'name' => 'Twilio',
                'description' => 'Send SMS, voice calls, and verify phone numbers via Twilio.',
                'icon' => 'device-phone-mobile',
                'color' => '#F22F46',
                'node_kind' => 'action',
                'credential_type' => 'twilio',
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_sms', 'send_whatsapp', 'make_call', 'send_verification', 'check_verification'], 'default' => 'send_sms'],
                        'to' => ['type' => 'string'],
                        'from' => ['type' => 'string'],
                    ],
                    'required' => ['operation', 'to', 'from'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'sid' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── AWS S3 ────────────────────────────────────────────────────
            [
                'category' => 'aws-s3',
                'type' => 'aws_s3.upload_file',
                'name' => 'AWS S3',
                'description' => 'Upload, download, list, and manage objects in Amazon S3 buckets.',
                'icon' => 'circle-stack',
                'color' => '#FF9900',
                'node_kind' => 'action',
                'credential_type' => 'aws',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['upload_file', 'download_file', 'list_objects', 'delete_object', 'get_signed_url', 'copy_object'], 'default' => 'upload_file'],
                        'bucket' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'region' => ['type' => 'string', 'default' => 'us-east-1'],
                        'acl' => ['type' => 'string', 'enum' => ['private', 'public-read', 'public-read-write'], 'default' => 'private'],
                    ],
                    'required' => ['operation', 'bucket', 'key'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                        'content_type' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'bucket' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── Dropbox ───────────────────────────────────────────────────
            [
                'category' => 'dropbox',
                'type' => 'dropbox.upload_file',
                'name' => 'Dropbox',
                'description' => 'Upload, download, share, and manage files in Dropbox.',
                'icon' => 'archive-box',
                'color' => '#0061FF',
                'node_kind' => 'action',
                'credential_type' => 'dropbox',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['upload_file', 'download_file', 'list_folder', 'delete_file', 'move_file', 'copy_file', 'create_folder', 'get_shared_link'], 'default' => 'upload_file'],
                        'path' => ['type' => 'string'],
                        'mode' => ['type' => 'string', 'enum' => ['add', 'overwrite', 'update'], 'default' => 'add'],
                    ],
                    'required' => ['operation', 'path'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'path_lower' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── OpenAI ────────────────────────────────────────────────────
            [
                'category' => 'openai',
                'type' => 'openai.chat_completion',
                'name' => 'OpenAI',
                'description' => 'Access OpenAI models: GPT-4o, DALL·E image generation, Whisper transcription, and embeddings.',
                'icon' => 'sparkles',
                'color' => '#10A37F',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0020,
                'latency_hint_ms' => 3000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['chat_completion', 'image_generation', 'transcription', 'text_to_speech', 'embeddings', 'moderation'], 'default' => 'chat_completion'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                        'system_prompt' => ['type' => 'string'],
                        'temperature' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2, 'default' => 0.7],
                        'max_tokens' => ['type' => 'integer', 'default' => 1024],
                    ],
                    'required' => ['operation', 'model'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                        'messages' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                        'usage' => ['type' => 'object'],
                    ],
                ],
            ],

            // ── MySQL ─────────────────────────────────────────────────────
            [
                'category' => 'mysql',
                'type' => 'mysql.query',
                'name' => 'MySQL',
                'description' => 'Execute queries, insert, update, and delete records in MySQL databases.',
                'icon' => 'circle-stack',
                'color' => '#4479A1',
                'node_kind' => 'action',
                'credential_type' => 'mysql',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['select', 'insert', 'update', 'delete', 'execute'], 'default' => 'select'],
                        'table' => ['type' => 'string'],
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'default' => 100],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                        'where' => ['type' => 'object'],
                        'params' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'rows' => ['type' => 'array'],
                        'affected_rows' => ['type' => 'integer'],
                        'insert_id' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── PostgreSQL ────────────────────────────────────────────────
            [
                'category' => 'postgresql',
                'type' => 'postgresql.query',
                'name' => 'PostgreSQL',
                'description' => 'Execute queries, insert, update, and delete records in PostgreSQL databases.',
                'icon' => 'circle-stack',
                'color' => '#336791',
                'node_kind' => 'action',
                'credential_type' => 'postgresql',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['select', 'insert', 'update', 'delete', 'execute', 'upsert'], 'default' => 'select'],
                        'table' => ['type' => 'string'],
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'default' => 100],
                        'schema' => ['type' => 'string', 'default' => 'public'],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                        'where' => ['type' => 'object'],
                        'params' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'rows' => ['type' => 'array'],
                        'affected_rows' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── MongoDB ───────────────────────────────────────────────────
            [
                'category' => 'mongodb',
                'type' => 'mongodb.find',
                'name' => 'MongoDB',
                'description' => 'Find, insert, update, and delete documents in MongoDB collections.',
                'icon' => 'circle-stack',
                'color' => '#47A248',
                'node_kind' => 'action',
                'credential_type' => 'mongodb',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['find', 'find_one', 'insert_one', 'insert_many', 'update_one', 'update_many', 'delete_one', 'delete_many', 'aggregate', 'count'], 'default' => 'find'],
                        'collection' => ['type' => 'string'],
                        'database' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'default' => 100],
                    ],
                    'required' => ['operation', 'collection'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'filter' => ['type' => 'object'],
                        'document' => ['type' => 'object'],
                        'pipeline' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'documents' => ['type' => 'array'],
                        'matched_count' => ['type' => 'integer'],
                        'modified_count' => ['type' => 'integer'],
                        'inserted_id' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── Redis ─────────────────────────────────────────────────────
            [
                'category' => 'redis',
                'type' => 'redis.get',
                'name' => 'Redis',
                'description' => 'Get, set, delete keys; work with lists, sets, hashes, and pub/sub in Redis.',
                'icon' => 'server',
                'color' => '#DC382D',
                'node_kind' => 'action',
                'credential_type' => 'redis',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['get', 'set', 'delete', 'exists', 'expire', 'ttl', 'lpush', 'rpush', 'lpop', 'rpop', 'lrange', 'hget', 'hset', 'hgetall', 'hdel', 'sadd', 'smembers', 'publish', 'keys', 'incr', 'decr'], 'default' => 'get'],
                        'key' => ['type' => 'string'],
                        'ttl' => ['type' => 'integer'],
                        'database' => ['type' => 'integer', 'default' => 0],
                    ],
                    'required' => ['operation', 'key'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['description' => 'Value to store'],
                        'field' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['description' => 'Retrieved value'],
                        'exists' => ['type' => 'boolean'],
                        'ttl' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        foreach ($nodes as $nodeData) {
            $categorySlug = $nodeData['category'];
            unset($nodeData['category']);

            $nodeData['category_id'] = $categories[$categorySlug];
            $nodeData['is_active'] = ! in_array($nodeData['type'], self::DISABLED_NODES);
            $nodeData['is_premium'] ??= false;

            Node::query()->updateOrCreate(
                ['type' => $nodeData['type']],
                $nodeData,
            );
        }
    }
}
