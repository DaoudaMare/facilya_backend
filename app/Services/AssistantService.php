<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class AssistantService
{
    public function __construct(
        protected GeminiClient $gemini,
        protected AssistantContextService $context,
    ) {}

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return array{reply: string, model: string|null}
     */
    public function ask(
        string $message,
        ?User $user = null,
        array $history = [],
        ?string $departure = null,
        ?string $arrival = null,
    ): array {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException('Le message ne peut pas être vide.');
        }

        $catalog = $this->context->build($user, $departure, $arrival);

        $input = <<<PROMPT
Contexte métier Facilya (données issues de la base, à utiliser pour répondre):
{$catalog}

Question du client:
{$message}
PROMPT;

        $result = $this->gemini->generate($input, $this->systemInstruction(), $history);

        $reply = $result['reply'] !== ''
            ? $result['reply']
            : 'Je n’ai pas pu formuler de réponse. Reformulez votre question.';

        return [
            'reply' => $reply,
            'model' => $result['model'],
        ];
    }

    protected function systemInstruction(): string
    {
        return <<<'TXT'
Tu es l’assistant client de Facilya, une application de transferts d’argent et de billets de voyage.
Réponds en français, de façon claire, concise et utile.
Base-toi uniquement sur le contexte métier fourni (catalogue, frais, promotions, trajets, transactions du client).
Si l’information manque dans le contexte, dis-le clairement et propose une action concrète (ex. vérifier un corridor, un réseau, ou une référence de transaction).
Ne révèle jamais de secrets techniques, clés API, ni données sensibles non présentes dans le contexte.
Ne invente pas de prix, horaires ou statuts.
N’exécute aucune action (paiement, transfert, réservation) : tu guides seulement.
TXT;
    }
}
