<?php

namespace App\Providers;

/**
 * Prepara os textos das questões para o aluno. O pipeline de geração escreve as justificativas citando os
 * trechos de livro pelo código ("[6af4b26d9bd8e60f727a0dfb]", "o chunk ... descreve"). O aluno nunca vê
 * linguagem de sistema: os códigos somem e "o chunk"/"o trecho" vira o livro de onde a informação veio
 * ("o livro «Neurociências»"), quando dá para saber qual é (pelo código ou porque a questão cita um só
 * livro); quando não dá, "o material de referência". O texto original continua em `questoes.bruto`.
 */
class TextoAlunoService
{
    private const ID = '[0-9a-f]{24}';
    private const PALAVRA = '(?:(?:chunks?|trechos?)\s+)?';
    private const LISTA = self::PALAVRA . self::ID . '(?:\s*(?:[;,]|\se)\s*' . self::PALAVRA . self::ID . ')*';

    // "(ID)", "[ID]", "(chunk ID)", "(ID; ID)", "[ID, ID]": o grupo inteiro some, com o espaço antes.
    private const GRUPO_DE_IDS = '/\s*[\(\[]\s*' . self::LISTA . '\s*[\)\]]/u';
    // "o chunk ID descreve...": a referência vira o livro daquele código.
    private const FONTE_COM_ID = '/\b(?:chunk|trecho)s?\s+(' . self::ID . ')(?:\s*(?:[;,]|\se)\s*' . self::ID . ')*/iu';
    private const A_PARTIR_DE_ID = '/,?\s*a partir d[eo]s?\s+' . self::ID . '/u';
    private const ID_SOLTO = '/\s*' . self::ID . '/u';
    // "o chunk", "esse trecho", "segundo o trecho", "no chunk citado": menção à fonte sem código.
    private const DETERMINANTES = '(?:o|os|e?sse|e?ste|nesse|neste|desse|deste|aquele|naquele|no|nos|do|dos|ao|pelo|mesmo)';
    private const CHUNK_SEM_ID = '/\bchunks?\b(?:\s+citados?)?/iu';
    private const TRECHO_META = '/\b(' . self::DETERMINANTES . '(?:\s+mesmo)?)\s+trechos?\b(?:\s+citados?)?/iu';

    public function __construct(private ReferenciasService $referencias)
    {
    }

    /** `semIdsDeChunk` com as fontes da própria questão. */
    public function paraAluno(?string $texto, array $referencias): string
    {
        [$porId, $livros] = $this->referencias->fontesDaQuestao($referencias);

        return $this->semIdsDeChunk((string) $texto, $porId, $livros);
    }

    /**
     * @param  array<string, string>  $fontesPorId  código do trecho => título curto do livro
     * @param  list<string>  $livros  títulos curtos dos livros citados pela questão
     */
    public function semIdsDeChunk(string $texto, array $fontesPorId = [], array $livros = []): string
    {
        if ($texto === '') {
            return $texto;
        }

        $fonteDe = function (?string $id) use ($fontesPorId, $livros): string {
            if ($id !== null && $id !== '' && isset($fontesPorId[$id])) {
                return "livro «{$fontesPorId[$id]}»";
            }

            return count($livros) === 1 ? "livro «{$livros[0]}»" : 'material de referência';
        };

        // "chunk ID" ou "chunks ID e ID": o(s) livro(s) desses códigos, no número certo.
        $fontesCitadas = function (string $trecho) use ($fontesPorId, $fonteDe): string {
            preg_match_all('/' . self::ID . '/u', $trecho, $m);
            $ids = $m[0];
            $nomes = array_values(array_unique(array_map(
                fn (string $id) => $fontesPorId[$id],
                array_filter($ids, fn (string $id) => isset($fontesPorId[$id])),
            )));
            if (count($ids) <= 1 || count($nomes) === 1) {
                return $fonteDe($ids[0] ?? null);
            }
            if ($nomes && count($nomes) === count(array_unique($ids))) {
                return 'livros ' . implode(' e ', array_map(fn (string $n) => "«{$n}»", $nomes));
            }

            return 'materiais de referência';
        };

        $semCitado = fn (string $trecho): string => preg_replace('/\s+citados?$/iu', '', $trecho);
        $pluralSemId = fn (string $trecho): string => str_ends_with(rtrim(mb_strtolower($trecho)), 's')
            ? 'materiais de referência'
            : $fonteDe(null);

        $t = preg_replace(self::GRUPO_DE_IDS, '', $texto);
        $t = preg_replace_callback(self::FONTE_COM_ID, fn (array $m) => $fontesCitadas($m[0]), $t);
        $t = preg_replace(self::A_PARTIR_DE_ID, '', $t);
        $t = preg_replace(self::ID_SOLTO, '', $t); // rede de segurança: nenhum código sobrevive
        $t = preg_replace_callback(self::CHUNK_SEM_ID, fn (array $m) => $pluralSemId($semCitado($m[0])), $t);
        $t = preg_replace_callback(self::TRECHO_META, fn (array $m) => $m[1] . ' ' . $pluralSemId($semCitado($m[0])), $t);
        $t = preg_replace('/\(\s*\)|\[\s*\]/u', '', $t);   // parênteses que ficaram vazios
        $t = preg_replace('/[ \t]+([.,;:])/u', '$1', $t);  // espaço solto antes de pontuação
        $t = preg_replace('/([,;])\s*\1/u', '$1', $t);     // ", [ID]," deixaria ",,"
        $t = preg_replace('/[ \t]{2,}/u', ' ', $t);

        return preg_replace('/^\s+|\s+$/u', '', $t);
    }
}
