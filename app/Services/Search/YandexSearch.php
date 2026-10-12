<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Поиск в Яндексе через Search API v2 (синхронный режим).
 *
 * Ответ приходит XML-ом в base64 внутри JSON. Из него берём только
 * то, что нужно для источников: адрес, домен, заголовок и короткие
 * отрывки текста, где встретился запрос.
 */
class YandexSearch
{
    private const ENDPOINT = 'https://searchapi.api.cloud.yandex.net/v2/web/search';

    public static function enabled(): bool
    {
        return filled(config('services.yandex_search.key'))
            && filled(config('services.yandex_search.folder'));
    }

    /**
     * @return list<array{url: string, domain: string, title: string, passages: list<string>}>
     *
     * @throws RuntimeException если Яндекс ответил ошибкой
     */
    public function search(string $query, int $limit = 10): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Api-Key '.config('services.yandex_search.key'),
        ])
            ->timeout(20)
            ->post(self::ENDPOINT, [
                'query' => [
                    'searchType' => 'SEARCH_TYPE_RU',
                    // Запрос длиннее 400 знаков Яндекс не принимает
                    'queryText' => mb_substr(trim($query), 0, 400),
                    'familyMode' => 'FAMILY_MODE_STRICT',
                ],
                'groupSpec' => [
                    'groupMode' => 'GROUP_MODE_DEEP',
                    'groupsOnPage' => (string) $limit,
                    'docsInGroup' => '1',
                ],
                'maxPassages' => '3',
                'region' => '225',
                'l10n' => 'LOCALIZATION_RU',
                'folderId' => config('services.yandex_search.folder'),
                'responseFormat' => 'FORMAT_XML',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Яндекс ответил '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $xml = base64_decode((string) $response->json('rawData'), true);

        if ($xml === false || $xml === '') {
            throw new RuntimeException('Яндекс прислал пустой ответ');
        }

        return $this->parse($xml);
    }

    /**
     * @return list<array{url: string, domain: string, title: string, passages: list<string>}>
     */
    public function parse(string $xml): array
    {
        $doc = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($doc === false) {
            throw new RuntimeException('Не разобрали ответ Яндекса');
        }

        // Ошибка приходит внутри XML с кодом 200: например, «ничего
        // не нашлось» (код 15) — это не сбой, а пустой результат
        $error = $doc->response->error ?? null;

        if ($error !== null && (string) $error !== '') {
            if ((string) $error['code'] === '15') {
                return [];
            }

            throw new RuntimeException('Яндекс: '.trim((string) $error));
        }

        $results = [];

        foreach ($doc->xpath('//group/doc') ?: [] as $item) {
            $url = trim((string) $item->url);

            if (! preg_match('#^https?://#i', $url)) {
                continue;
            }

            $passages = [];

            foreach ($item->passages->passage ?? [] as $passage) {
                $passages[] = self::text($passage);
            }

            $results[] = [
                'url' => $url,
                'domain' => trim((string) $item->domain),
                'title' => self::text($item->title),
                'passages' => array_values(array_filter($passages)),
            ];
        }

        return $results;
    }

    /** Текст узла без разметки подсветки (<hlword>) */
    private static function text(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        $xml = (string) $node->asXML();

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? '');
    }
}
