<?php

namespace Tests\Unit;

use App\Providers\ImportacaoQuestoesService;
use App\Providers\ReferenciasService;
use App\Providers\TextoAlunoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Textos e referências mostrados ao aluno, sem banco. Portados dos testes do teste local (tests/test_regras.py).
 */
class TextoAlunoServiceTest extends TestCase
{
    private const ID = '6a26eef3c9df895c5879b88c';
    private const ID2 = '6a26eef3c9df895c5879b88d';
    private const LIVRO_A = 'Neuroanatomia Funcional';
    private const LIVRO_B = 'Tratado de Fisiologia Médica';
    private const FONTES = [self::ID => self::LIVRO_A, self::ID2 => self::LIVRO_B];

    private ReferenciasService $referencias;
    private TextoAlunoService $texto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->referencias = new ReferenciasService();
        $this->texto = new TextoAlunoService($this->referencias);
    }

    #[DataProvider('textos')]
    public function test_nenhum_codigo_de_trecho_nem_linguagem_de_sistema_chega_ao_aluno(
        string $entrada,
        array $fontes,
        array $livros,
        string $esperado,
    ): void {
        $this->assertSame($esperado, $this->texto->semIdsDeChunk($entrada, $fontes, $livros));
    }

    public static function textos(): array
    {
        $id = self::ID;
        $id2 = self::ID2;
        $a = self::LIVRO_A;
        $b = self::LIVRO_B;

        return [
            // códigos entre parênteses ou colchetes somem
            ["cegueira do campo direito ({$id}). Os axônios", [], [], 'cegueira do campo direito. Os axônios'],
            ["cruzam a linha média [{$id}]. Essas fibras", [], [], 'cruzam a linha média. Essas fibras'],
            ["em cada olho ({$id}; {$id2}).", [], [], 'em cada olho.'],
            ["em cada olho ({$id}, {$id2}) e segue", [], [], 'em cada olho e segue'],
            ["até o tálamo (chunk {$id}). Nesse ponto", [], [], 'até o tálamo. Nesse ponto'],
            ["até o tálamo, [{$id}], e depois", [], [], 'até o tálamo, e depois'],
            ["(raciocínio do aluno, a partir de {$id})", [], [], '(raciocínio do aluno)'],
            ["texto solto {$id} no meio", [], [], 'texto solto no meio'],
            // "chunk ID": vira o livro daquele código; sem saber qual é, o material de referência
            ["O chunk {$id} descreve as amnésias.", self::FONTES, [], "O livro «{$a}» descreve as amnésias."],
            ["O chunk {$id} descreve as amnésias.", [], [], 'O material de referência descreve as amnésias.'],
            ["conforme o chunk {$id}.", self::FONTES, [], "conforme o livro «{$a}»."],
            ["Na tabela do chunk {$id}, o hemisfério", [], [], 'Na tabela do material de referência, o hemisfério'],
            ["Os chunks {$id} e {$id2} indicam", self::FONTES, [], "Os livros «{$a}» e «{$b}» indicam"],
            ["Os chunks {$id} e {$id2} indicam", [], [], 'Os materiais de referência indicam'],
            // menções sem código: o livro único da questão, ou o material de referência
            ['O chunk descreve o sulco.', [], [$a], "O livro «{$a}» descreve o sulco."],
            ['O chunk descreve o sulco.', [], [$a, $b], 'O material de referência descreve o sulco.'],
            ['descrito no chunk citado.', [], [], 'descrito no material de referência.'],
            ['Segundo o trecho, a via dorsal reúne', [], [$b], "Segundo o livro «{$b}», a via dorsal reúne"],
            ['mas esse trecho não as associa', [], [], 'mas esse material de referência não as associa'],
            ['consta dos chunks desta lista', [], [], 'consta dos materiais de referência desta lista'],
            ['aparece no mesmo trecho com funções', [], [], 'aparece no mesmo material de referência com funções'],
            // usos legítimos de "trecho" e textos sem menção ficam intactos
            ['sobretudo em trechos sem iluminação', [], [], 'sobretudo em trechos sem iluminação'],
            ['o paciente não trecho', [], [], 'o paciente não trecho'], // "o" dentro de "não" não é determinante
            ['sem nenhum código aqui.', [], [], 'sem nenhum código aqui.'],
            ['', [], [], ''],
        ];
    }

    public function test_citacao_completa_e_titulo_curto(): void
    {
        $bear = [
            'file_name' => 'Neurociencias Desvendando o Sistema Nervoso.pdf',
            'titulo' => 'Neurociências: Desvendando o Sistema Nervoso',
            'autor' => 'Mark F. Bear; Barry W. Connors e Michael A. Paradiso',
            'edicao' => 4, 'editora' => 'Artmed', 'publicacao' => 2017,
        ];
        $kandel = [
            'file_name' => 'Principios De Neurociencias.pdf',
            'titulo' => 'Princípios de Neurociências',
            'autor' => 'Eric R. Kandel; James H. Schwartz; Thomas M. Jessell; Steven A. Siegelbaum; A. J. Hudspeth',
            'edicao' => 5, 'editora' => 'Artmed / McGraw Hill Education', 'publicacao' => 2014,
        ];

        $this->assertSame(
            'Neurociências: Desvendando o Sistema Nervoso — Mark F. Bear; Barry W. Connors e Michael A. Paradiso (4ª ed., Artmed, 2017)',
            $this->referencias->citacao($bear),
        );
        $this->assertSame(
            'Princípios de Neurociências — Eric R. Kandel et al. (5ª ed., Artmed / McGraw Hill Education, 2014)',
            $this->referencias->citacao($kandel),
        );
        $this->assertSame('Teste Livro', $this->referencias->citacao(['file_name' => 'Livro Teste_Livro.pdf']));
        $this->assertSame('Neurociências', $this->referencias->tituloCurto('Neurociências: Desvendando o Sistema Nervoso'));
        $this->assertSame('Histologia Básica', $this->referencias->tituloCurto('Histologia Básica'));
    }

    public function test_mesmo_livro_apesar_das_variacoes_no_nome_do_arquivo(): void
    {
        $chave = $this->referencias->chaveDoLivro('Livro Cem bilhoes de neuronios Roberto Lent.pdf');

        $this->assertSame($chave, $this->referencias->chaveDoLivro('Livro Cem bilhoes de neuronios Roberto Lent'));
        $this->assertSame($chave, $this->referencias->chaveDoLivro('pasta/livro_Cem_bilhões_de_neurônios_Roberto_Lent.PDF'));
        $this->assertSame('compendio de psiquiatria', $this->referencias->chaveDoLivro('Compêndio de Psiquiatria.pdf'));
    }

    public function test_equivalencia_completa_os_metadados_e_unifica_o_nome_do_livro(): void
    {
        $catalogo = $this->referencias->catalogo([
            'Fisiologia Humana Uma Abordagem Integrada.pdf' => [
                'titulo' => 'Fisiologia Humana: Uma Abordagem Integrada', 'autor' => 'Dee Unglaub Silverthorn',
                'edicao' => 7, 'editora' => 'Artmed', 'publicacao' => 2017,
            ],
        ]);
        $ref = ['file_name' => 'Livro Fisiologia Humana  Silverthorn.pdf', 'chunk_id' => 'c1'];

        $sem = $this->referencias->enriquecer([$ref], $catalogo)[0];
        $this->assertArrayNotHasKey('titulo', $sem); // sem a equivalência declarada, nada é inferido

        $com = $this->referencias->enriquecer([$ref], $catalogo, [
            'Livro Fisiologia Humana  Silverthorn.pdf' => 'Fisiologia Humana Uma Abordagem Integrada.pdf',
        ])[0];
        $this->assertSame('Fisiologia Humana: Uma Abordagem Integrada', $com['titulo']);
        $this->assertSame('Dee Unglaub Silverthorn', $com['autor']);
        $this->assertSame('Fisiologia Humana Uma Abordagem Integrada.pdf', $com['file_name']);
        $this->assertSame('c1', $com['chunk_id']);
    }

    public function test_catalogo_nunca_sobrescreve_o_que_a_referencia_ja_tem(): void
    {
        $catalogo = $this->referencias->catalogo(['B.pdf' => ['titulo' => 'Título B', 'autor' => 'Autor B']]);

        $nova = $this->referencias->enriquecer([['file_name' => 'A.pdf', 'titulo' => 'Título A']], $catalogo, ['A.pdf' => 'B.pdf'])[0];

        $this->assertSame('Título A', $nova['titulo']);
        $this->assertSame('Autor B', $nova['autor']);
    }

    public function test_codigo_da_questao_ignora_maiusculas_espacos_e_segue_a_regra_do_pipeline(): void
    {
        $this->assertSame(
            ImportacaoQuestoesService::codigo("Um  caso\n clínico", 'Qual?', 'Vias visuais'),
            ImportacaoQuestoesService::codigo('um caso clínico', ' QUAL? ', 'vias visuais'),
        );
        $this->assertNotSame(ImportacaoQuestoesService::codigo('a', 'b', 'c'), ImportacaoQuestoesService::codigo('a', 'b', 'd'));
        // Mesmo valor que o pipeline (Python, `hash_questao`) gera para estes textos.
        $this->assertSame(
            'sha256:' . hash('sha256', 'um caso clínico|qual?|vias visuais'),
            ImportacaoQuestoesService::codigo('Um caso clínico', 'Qual?', 'Vias visuais'),
        );
    }
}
