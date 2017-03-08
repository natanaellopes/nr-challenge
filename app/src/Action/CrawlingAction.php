<?php

namespace App\Action;

use Goutte\Client;
use Slim\Http\Request;
use Slim\Http\Response;

class CrawlingAction
{

    const URL_CL_DF = 'http://www.cl.df.gov.br/pt_PT/pregoes';
    const URL_CNPQ = 'http://www.cnpq.br/web/guest/licitacoes';
    const URL_CAMARA = 'http://www2.camara.leg.br/transparencia/licitacoes/editais/pregaoeletronico.html';

    private $results = [];
    private $tmpAttach;

    public function __invoke(Request $request, Response $response)
    {
        return $response->withJson($this->getResults());
    }

    private function getResults()
    {
        $this->extractCnpqData();
        $this->extractClDfData();
        $this->extractCamaraData();

        return $this->results;
    }

    private function extractCnpqData()
    {
        $client = new Client();

        $crawler = $client->request('GET', self::URL_CNPQ);

        $crawler->filter('.resultado-licitacao')->filterXPath('//table/tbody/tr')->each(function ($node) {

            $this->tmpAttach = [];

            $node->filter('ul.download-list')->filter('li')->each(function ($link) {
                $this->tmpAttach[] = [
                    'name' => trim($link->text()),
                    'attachment' => 'http://www.cnpq.br' . $link->filter('a')->attr('href'),
                ];
            });

            $this->results[] = [
                'name' => $node->filter('h4.titLicitacao')->text(),
                'origin' => 'CNPq',
                'attachments' => $this->tmpAttach
            ];
        });
    }

    private function extractClDfData()
    {
        $client = new Client();

        $crawlerToCurrent = $client->request('GET', self::URL_CL_DF);

        $current = $crawlerToCurrent->filter('.results-row.last')->filter('a')->attr('href');

        $crawler = $client->request('GET', $current);

        $crawler->filter('#portlet_110_INSTANCE_ou5V')->filter('.results-grid')->first()->filterXPath('//table/tr/td[1]')->filter('a')->each(function ($node) {

            if ($node->filter('a')->text() != '') {

                $title = $node->filter('strong')->text();

                $this->tmpAttach = [];

                $UrlAttach = $node->filter('a')->attr('href');

                $newClient = new Client();
                $crawlerToAttach = $newClient->request('GET', $UrlAttach);

                $crawlerToAttach->filter('#portlet_110_INSTANCE_ou5V')->filter('.results-grid')->first()->filterXPath('//table/tr/td[1]')->filter('a')->each(function ($link) {

                    $newClient = new Client();
                    $crawlerToAttachFinal = $newClient->request('GET', $link->filter('a')->attr('href'));

                    $attachFinal = $crawlerToAttachFinal->filter('#portlet_110_INSTANCE_ou5V')->filter('.results-grid')->first()->filterXPath('//table/tr[3]/td[1]')->filter('a')->attr('href');

                    $this->tmpAttach[] = [
                        'name' => trim($link->text()),
                        'attachment' => $attachFinal,
                    ];

                });

                $this->results[] = [
                    'name' => $title,
                    'origin' => 'CL DF',
                    'attachments' => $this->tmpAttach
                ];
            }

        });


    }

    private function extractCamaraData()
    {
        $client = new Client();

        $crawler = $client->request('GET', self::URL_CAMARA);

        $crawler->filter('#content-core')->filterXPath('//table/tbody[2]')->each(function ($node) {

            $this->tmpAttach = [];

            $node->filterXPath('//tr[1]/td[1]')->filter('a.external-link')->each(function ($link) {
                $this->tmpAttach[] = [
                    'name' => 'Edital',
                    'attachment' => $link->filter('a')->attr('href'),
                ];
            });

            $name = trim($node->filterXPath('//tr[1]/td[2]')->text());

            $date = trim($node->filterXPath('//tr[1]/td[3]')->text());

            $this->results[] = [
                'name' => $name . ' ' . $date,
                'origin' => 'Camara',
                'attachments' => $this->tmpAttach
            ];

        });
    }

}