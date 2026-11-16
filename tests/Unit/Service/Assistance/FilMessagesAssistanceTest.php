<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Entity\MessageAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Service\Assistance\FilMessagesAssistance;
use App\Service\Assistance\SeparateurCitation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FilMessagesAssistanceTest extends TestCase
{
    private FilMessagesAssistance $fil;

    protected function setUp(): void
    {
        $this->fil = new FilMessagesAssistance(new SeparateurCitation());
    }

    private function message(string $id, string $heure, ?string $repondA = null, SensMessageAssistanceEnum $sens = SensMessageAssistanceEnum::ENTRANT): MessageAssistance
    {
        return (new MessageAssistance())
            ->setMessageId($sens === SensMessageAssistanceEnum::NOTE ? null : '<' . $id . '@x>')
            ->setInReplyTo($repondA !== null ? '<' . $repondA . '@x>' : null)
            ->setSujet($id)
            ->setSens($sens)
            ->setDateEnvoi(new DateTimeImmutable('2026-11-13 ' . $heure));
    }

    /**
     * @param list<array{message: MessageAssistance, profondeur: int}> $arbre
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function resume(array $arbre): array
    {
        return [
            array_map(static fn (array $n) => $n['message']->getSujet(), $arbre),
            array_map(static fn (array $n) => $n['profondeur'], $arbre),
        ];
    }

    public function testReponsesSontPlaceesSousLeurParent(): void
    {
        $arbre = $this->fil->arbre([
            $this->message('D', '11:00', 'A'),
            $this->message('C', '10:00', 'B'),
            $this->message('A', '09:00'),
            $this->message('B', '09:30', 'A'),
        ]);

        self::assertSame([['A', 'B', 'C', 'D'], [0, 1, 2, 1]], $this->resume($arbre));
    }

    public function testParentAbsentEtNoteSontDesRacinesTrieesParDate(): void
    {
        $arbre = $this->fil->arbre([
            $this->message('X', '10:00', 'inconnu'),
            $this->message('N', '09:30', null, SensMessageAssistanceEnum::NOTE),
            $this->message('A', '09:00'),
        ]);

        self::assertSame([['A', 'N', 'X'], [0, 0, 0]], $this->resume($arbre));
    }

    public function testProfondeurPlafonneeA3(): void
    {
        $messages = [$this->message('M0', '09:00')];
        for ($i = 1; $i < 6; ++$i) {
            $messages[] = $this->message('M' . $i, sprintf('09:%02d', $i), 'M' . ($i - 1));
        }

        self::assertSame([0, 1, 2, 3, 3, 3], $this->resume($this->fil->arbre($messages))[1]);
    }

    public function testFilEnBoucleAfficheChaqueMessageUneFois(): void
    {
        $arbre = $this->fil->arbre([
            $this->message('A', '09:00', 'B'),
            $this->message('B', '09:30', 'A'),
            $this->message('C', '10:00', 'C'),
        ]);

        $sujets = $this->resume($arbre)[0];
        sort($sujets);
        self::assertSame(['A', 'B', 'C'], $sujets);
    }

    public function testExtraitSansHistoriqueEtTronque(): void
    {
        $message = (new MessageAssistance())->setCorpsTexte("Merci pour la correction.\n\nLe 12/11/2026 à 17:58, X a écrit :\n> ancien");
        self::assertSame('Merci pour la correction.', $this->fil->extrait($message));

        $long = (new MessageAssistance())->setCorpsTexte(str_repeat('a', 300));
        self::assertSame(101, mb_strlen($this->fil->extrait($long)));
        self::assertStringEndsWith('…', $this->fil->extrait($long));

        self::assertSame('', $this->fil->extrait(new MessageAssistance()));
    }
}
