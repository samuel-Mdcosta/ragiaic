<?php

namespace App\Providers;

use App\Exceptions\FeedbackIndisponivelException;
use App\Models\FeedbackDesempenho;
use App\Models\TentativaQuizz;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Feedback de desempenho gerado pela IA a partir do rendimento do aluno.
 * Fica salvo e só é gerado de novo quando o aluno conclui novas tentativas.
 */
class FeedbackDesempenhoService
{
    public function __construct(private DesempenhoService $desempenhoService)
    {
    }

    /**
     * @throws FeedbackIndisponivelException
     */
    public function obterFeedback(int $usuarioId): array
    {
        $totalTentativas = TentativaQuizz::where('usuario_id', $usuarioId)->count();

        $ultimo = FeedbackDesempenho::where('usuario_id', $usuarioId)->orderByDesc('id')->first();
        if ($ultimo && $ultimo->total_tentativas === $totalTentativas) {
            return $this->formatar($ultimo, true);
        }

        $desempenhoPorTema = $this->desempenhoService->calcularDesempenhoPorTema($usuarioId);
        if (empty($desempenhoPorTema)) {
            return [
                'status'           => 'sem_dados',
                'feedback'         => null,
                'total_tentativas' => $totalTentativas,
                'gerado_em'        => null,
                'em_cache'         => false,
            ];
        }

        $resposta = $this->chamarMotorIA(
            $this->desempenhoService->montarDadosParaIA($usuarioId, $desempenhoPorTema)
        );

        $registro = FeedbackDesempenho::create([
            'usuario_id'       => $usuarioId,
            'total_tentativas' => $totalTentativas,
            'conteudo'         => $this->anexarNumeros($resposta['feedback'], $desempenhoPorTema),
            'uso_tokens'       => $resposta['uso_tokens'] ?? null,
        ]);

        return $this->formatar($registro, false);
    }

    /**
     * @throws FeedbackIndisponivelException
     */
    private function chamarMotorIA(array $dados): array
    {
        $url = rtrim(config('services.tutor_ia.url'), '/') . '/feedback';

        try {
            $resposta = Http::timeout((int) config('services.tutor_ia.timeout'))
                ->acceptJson()
                ->withHeaders(['X-API-Key' => config('services.tutor_ia.secret')])
                ->post($url, $dados);
        } catch (ConnectionException $e) {
            Log::warning('Motor de IA inacessível ao gerar feedback de desempenho', ['erro' => $e->getMessage()]);
            throw new FeedbackIndisponivelException('Não foi possível conectar ao serviço de IA.', 0, $e);
        }

        if ($resposta->failed() || !is_array($resposta->json('feedback'))) {
            Log::warning('Motor de IA não gerou o feedback de desempenho', [
                'status' => $resposta->status(),
                'corpo'  => mb_substr($resposta->body(), 0, 500),
            ]);
            throw new FeedbackIndisponivelException('O serviço de IA não conseguiu gerar o feedback.');
        }

        return $resposta->json();
    }

    /**
     * Guarda junto de cada área os números que a originaram (taxa, nível, tendência),
     * para o front e para análises futuras do feedback gerado.
     */
    private function anexarNumeros(array $feedback, array $desempenhoPorTema): array
    {
        $porTema = collect($desempenhoPorTema)->keyBy('tema');

        $feedback['areas_prioritarias'] = array_map(function ($area) use ($porTema) {
            $numeros = $porTema->get($area['tema'] ?? null);

            return $numeros ? array_merge($area, [
                'taxa_acerto'  => $numeros['taxa_acerto'],
                'taxa_recente' => $numeros['taxa_recente'],
                'nivel'        => $numeros['nivel'],
                'tendencia'    => $numeros['tendencia'],
                'questoes'     => $numeros['questoes'],
            ]) : $area;
        }, $feedback['areas_prioritarias'] ?? []);

        return $feedback;
    }

    private function formatar(FeedbackDesempenho $registro, bool $emCache): array
    {
        return [
            'status'           => 'ok',
            'feedback'         => $registro->conteudo,
            'total_tentativas' => $registro->total_tentativas,
            'gerado_em'        => optional($registro->created_at)->toIso8601String(),
            'em_cache'         => $emCache,
        ];
    }
}
