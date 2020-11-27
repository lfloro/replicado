<?php

namespace Uspdev\Replicado;

class Lattes
{
    /**
     * Recebe o número USP e retorna o ID Lattes da pessoa.
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function id($codpes)
	{
	    $query = "SELECT idfpescpq from DIM_PESSOA_XMLUSP WHERE codpes = convert(int,:codpes)";
		$param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetch($query, $param);
        if($result) return $result['idfpescpq'];
        return false;
    }
    
    /**
     * Recebe o número USP e retorna o binário zip do lattes
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function getZip($codpes){
        putenv('REPLICADO_SYBASE=0'); # hotfix -  o utf8_encode estraga o zip
        $query = "SELECT imgarqxml from DIM_PESSOA_XMLUSP WHERE codpes = convert(int,:codpes)";
        $param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetch($query, $param);

        if(!empty($result)) return $result['imgarqxml'];
        putenv('REPLICADO_SYBASE=1'); # hotfix -  o utf8_encode estraga o zip
        return false;
    }

    /**
     * Recebe o número USP e salva o zip do lattes
     * 
     * @param Integer $codpes
     * @return Bool
     */
    public static function saveZip($codpes, $to = '/tmp'){
        $content = self::getZip($codpes);
        if($content){
            $zipFile = fopen("{$to}/{$codpes}.zip", "w");
            fwrite($zipFile, $content); 
            fclose($zipFile);
            return true;
        }
        return false;
    }

    /**
     * Recebe o número USP e salva o xml do lattes
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function saveXml($codpes, $to = '/tmp'){
        $content = self::getZip($codpes);
        if($content){
            $xml = Uteis::unzip($content);
            $xmlFile = fopen("{$to}/{$codpes}.xml", "w");
            fwrite($xmlFile, $xml); 
            fclose($xmlFile);
            return true;
        }
        return false;
    }

    /**
     * Recebe o número USP e devolve XML do lattes
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function getXml($codpes){
        $zip = self::getZip($codpes);
        if(!$zip) return false;

        return Uteis::unzip($zip);
    }

    /**
     * Recebe o número USP e devolve json do lattes
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function getJson($codpes){
        $xml = self::getXml($codpes);
        if(!$xml) return false;

        return json_encode(simplexml_load_string($xml));
    }

    /**
     * Recebe o número USP e devolve array do lattes
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function getArray($codpes){
        $json = self::getJson($codpes);
        if(!$json) return false;
        return json_decode($json,TRUE);
    }

    /**
     * Recebe o número USP e devolve array dos prêmios e títulos cadastros no currículo Lattes,
     * com o respectivo ano de prêmiação
     * 
     * @param Integer $codpes
     * @return String|Bool
     */
    public static function getPremios($codpes){
        $lattes = self::getArray($codpes);
        if(!$lattes && !isset($lattes['DADOS-GERAIS'])) return false;

        $premios = $lattes['DADOS-GERAIS'];
        if(array_key_exists('PREMIOS-TITULOS',$premios)){
            $premios = $lattes['DADOS-GERAIS']['PREMIOS-TITULOS']['PREMIO-TITULO'];
            $nome_premios = [];
            foreach($premios as $p){
                if(!isset($p['@attributes']['NOME-DO-PREMIO-OU-TITULO'])){
                    return false;
                }else
                array_push($nome_premios, $p['@attributes']['NOME-DO-PREMIO-OU-TITULO'] . ' - Ano: ' . $p['@attributes']['ANO-DA-PREMIACAO']);
            }     
        return $nome_premios;
        }
        else return false;
     }
  
    /**
    * Recebe o número USP e devolve o resumo do currículo do lattes
    * 
    * @param Integer $codpes
    * @param String $idioma = Valores aceitos para idioma: 'pt' (português) e 'en' (inglês)
    * @return String|Bool
    * 
    */
    public static function getResumoCV($codpes, $idioma = 'pt'){
        $lattes = self::getArray($codpes);

        if(!$lattes) return false;

        $campo = 'TEXTO-RESUMO-CV-RH';
        if(strtolower($idioma) == 'en') $campo .= '-EN'; 
        $resumo_cv = isset($lattes['DADOS-GERAIS']['RESUMO-CV']['@attributes'][$campo]) 
                    ? $lattes['DADOS-GERAIS']['RESUMO-CV']['@attributes'][$campo]
                    : false;
        
        return $resumo_cv;
    }

    /**
    * Recebe o número USP e devolve array com os últimos artigos cadastrados no currículo Lattes,
    * com o respectivo título do artigo, nome da revista ou períodico, volume, número de páginas e ano de publicação
    *  
    * @param Integer $codpes = Número USP
    * @param Integer $limit = Número de artigos a serem retornados, se não preenchido, o valor default é 5
    * @return String|Bool
    */
    public static function getArtigos($codpes, $limit = 5){
        $lattes = self::getArray($codpes);
        if(!$lattes && !isset($lattes['PRODUCAO-BIBLIOGRAFICA'])) return false;
        $artigos = $lattes['PRODUCAO-BIBLIOGRAFICA'];

        if(array_key_exists('ARTIGOS-PUBLICADOS',$artigos)){
            $artigos = $lattes['PRODUCAO-BIBLIOGRAFICA']['ARTIGOS-PUBLICADOS']['ARTIGO-PUBLICADO'];
            //ordena em ordem decrescente.
            usort($artigos, function ($a, $b) {
                if(!isset($b['@attributes']['SEQUENCIA-PRODUCAO'])){
                    return 0;
                }
                return (int)$b['@attributes']['SEQUENCIA-PRODUCAO'] - (int)$a['@attributes']['SEQUENCIA-PRODUCAO'];
            });
            //verificação para saber se há apenas 1 artigo
            if(!isset($artigos[1]['@attributes']['SEQUENCIA-PRODUCAO'])){
                $aux = $artigos;
                $artigos = [];
                $artigos[0] = $aux;
            }         
            $i = 0;
            $ultimos_artigos = [];
            foreach($artigos as $val){
                if($limit != -1 && $i > ($limit - 1) ) break; $i++; //-1 retorna tudo
                $dados_basicos = (!isset($val['DADOS-BASICOS-DO-ARTIGO']) && isset($val[1])) ? 1 : 'DADOS-BASICOS-DO-ARTIGO';
                $detalhamento = (!isset($val['DETALHAMENTO-DO-ARTIGO']) && isset($val[2])) ? 2 : 'DETALHAMENTO-DO-ARTIGO';
               
                $aux_artigo = [
                    'TITULO-DO-ARTIGO' => $val[$dados_basicos]['@attributes']['TITULO-DO-ARTIGO'] ?? '',
                    'TITULO-DO-PERIODICO-OU-REVISTA' => $val[$detalhamento]['@attributes']['TITULO-DO-PERIODICO-OU-REVISTA'] ?? '',
                    'VOLUME' => $val[$detalhamento]['@attributes']['VOLUME'] ?? '',
                    'PAGINA-INICIAL' => $val[$detalhamento]['@attributes']['PAGINA-INICIAL'] ?? '',
                    'PAGINA-FINAL' => $val[$detalhamento]['@attributes']['PAGINA-FINAL'] ?? '',
                    'ANO' => $val[$dados_basicos]['@attributes']['ANO-DO-ARTIGO'] ?? '',
                ];
                array_push($ultimos_artigos, $aux_artigo);
            }
            return $ultimos_artigos;
        } else return false;
    }
}