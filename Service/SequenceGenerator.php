<?php

/*
 * This file is part of the TecnoCreaciones package.
 * 
 * (c) www.tecnocreaciones.com.ve
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tecnocreaciones\Bundle\ToolsBundle\Service;

/**
 * Description of SequenceGenerator
 *
 * @author Carlos Mendoza <inhack20@tecnocreaciones.com>
 */
class SequenceGenerator implements \Symfony\Component\DependencyInjection\ContainerAwareInterface
{
    private $container;
    
    function generateNext() {
        $doctrine = $this->getDoctrine();
        $qb = $this->createQueryBuilder('q');
        $qb->from('Tecnocreaciones\Vzla\EntityBundle\Entity\Country', 'q');
        $table = 'tabla';
        $field = 'description';
        
        $mode = 'next';
        $cat = 'CATEGORIA';
        $zone = 'ZONA';
        $mask = 'SIEMPRE-{dd}-{mm}-{yyyy}-{yy}-{000}';
        // Clean parameters
        $date = time(); // We use local year and month of PHP server to search numbers
            
        // but we should use local year and month of user
        // Extract value for mask counter, mask raz and mask offset
        if (!preg_match('/\{(0+)([@\+][0-9]+)?([@\+][0-9]+)?\}/i', $mask, $reg))
            return 'ErrorBadMask';
        $masktri = (isset($reg[1]) ? $reg[1] : '') . (isset($reg[2]) ? $reg[2] : '') . (isset($reg[3]) ? $reg[3] : '');
        $maskcounter = $reg[1];
        $maskraz = -1;
        $maskoffset = 0;
        if (strlen($maskcounter) < 2)
            return 'CounterMustHaveMoreThan2Digits';
        $maskrefclient_maskcounter = '';
        $maskrefclient = '';

        $masktype_value = "";
            $masktype = '';

        $maskwithonlyymcode = $mask;
        $maskwithonlyymcode = preg_replace('/\{(0+)([@\+][0-9]+)?([@\+][0-9]+)?\}/i', $maskcounter, $maskwithonlyymcode);
        $maskwithonlyymcode = preg_replace('/\{dd\}/i', 'dd', $maskwithonlyymcode);
        $maskwithonlyymcode = preg_replace('/\{(c+)(0*)\}/i', $maskrefclient, $maskwithonlyymcode);
        $maskwithonlyymcode = preg_replace('/\{(t+)\}/i', $masktype_value, $maskwithonlyymcode);
        $maskwithnocode = $maskwithonlyymcode;
        $maskwithnocode = preg_replace('/\{yyyy\}/i', 'yyyy', $maskwithnocode);
        $maskwithnocode = preg_replace('/\{CAT\}/i', 'yyyy', $maskwithnocode);
        $maskwithnocode = preg_replace('/\{ZONE\}/i', 'yyyy', $maskwithnocode);
        $maskwithnocode = preg_replace('/\{yy\}/i', 'yy', $maskwithnocode);
        $maskwithnocode = preg_replace('/\{y\}/i', 'y', $maskwithnocode);
        $maskwithnocode = preg_replace('/\{mm\}/i', 'mm', $maskwithnocode);
        // Now maskwithnocode = 0000ddmmyyyyccc for example
        // and maskcounter    = 0000 for example
        //print "maskwithonlyymcode=".$maskwithonlyymcode." maskwithnocode=".$maskwithnocode."\n<br>";
        // If an offset is asked
        if (!empty($reg[2]) && preg_match('/^\+/', $reg[2]))
            $maskoffset = preg_replace('/^\+/', '', $reg[2]);
        if (!empty($reg[3]) && preg_match('/^\+/', $reg[3]))
            $maskoffset = preg_replace('/^\+/', '', $reg[3]);

        // Define $sqlwhere
        // If a restore to zero after a month is asked we check if there is already a value for this year.
        if (!empty($reg[2]) && preg_match('/^@/', $reg[2]))
            $maskraz = preg_replace('/^@/', '', $reg[2]);
        if (!empty($reg[3]) && preg_match('/^@/', $reg[3]))
            $maskraz = preg_replace('/^@/', '', $reg[3]);
        if ($maskraz >= 0) {
            if ($maskraz > 12)
                return 'ErrorBadMaskBadRazMonth';

            // Define posy, posm and reg
            if ($maskraz > 1) {
                if (!preg_match('/^(.*)\{(y+)\}\{(m+)\}/i', $maskwithonlyymcode) && !preg_match('/^(.*)\{(m+)\}\{(y+)\}/i', $maskwithonlyymcode))
                    return 'ErrorCantUseRazInStartedYearIfNoYearMonthInMask';
                if (preg_match('/^(.*)\{(y+)\}\{(m+)\}/i', $maskwithonlyymcode, $reg)) {
                    $posy = 2;
                    $posm = 3;
                } elseif (preg_match('/^(.*)\{(m+)\}\{(y+)\}/i', $maskwithonlyymcode, $reg)) {
                    $posy = 3;
                    $posm = 2;
                }
                if (strlen($reg[$posy]) < 2)
                    return 'ErrorCantUseRazWithYearOnOneDigit';
            }
            else {
                if (!preg_match('/^(.*)\{(y+)\}/i', $maskwithonlyymcode))
                    return 'ErrorCantUseRazIfNoYearInMask';
                if (preg_match('/^(.*)\{(y+)\}/i', $maskwithonlyymcode, $reg)) {
                    $posy = 2;
                    $posm = 0;
                }
            }
            //print "x".$maskwithonlyymcode." ".$maskraz." ".$posy." ".$posm;
            // Define $yearcomp and $monthcomp (that will be use in the select where to search max number)
            $sqlwhere = '';
            $monthcomp = $maskraz;
            $yearoffset = 0;
            $yearcomp = 0;
            if (date("m", $date) < $maskraz) {
                $yearoffset = -1;
            } // If current month lower that month of return to zero, year is previous year
            if (strlen($reg[$posy]) == 4)
                $yearcomp = sprintf("%04d", date("Y", $date) + $yearoffset);
            if (strlen($reg[$posy]) == 2)
                $yearcomp = sprintf("%02d", date("y", $date) + $yearoffset);
            if (strlen($reg[$posy]) == 1)
                $yearcomp = substr(date("y", $date), 2, 1) + $yearoffset;
            $sqlwhere = '';
            $sqlwhere.='( (SUBSTRING(' . $field . ', ' . (strlen($reg[1]) + 1) . ', ' . strlen($reg[2]) . ') >= ' . $yearcomp;
            if ($monthcomp > 1) { // Test useless if monthcomp = 1 (or 0 is same as 1)
                if (strlen($reg[$posy]) == 4)
                    $yearcomp1 = sprintf("%04d", date("Y", $date) + $yearoffset + 1);
                if (strlen($reg[$posy]) == 2)
                    $yearcomp1 = sprintf("%02d", date("y", $date) + $yearoffset + 1);
                // FIXME If mask is {mm}{yy}, sqlwhere is wrong here
                $sqlwhere.=' AND SUBSTRING(' . $field . ', ' . (strlen($reg[1]) + strlen($reg[2]) + 1) . ', ' . strlen($reg[3]) . ') >= ' . $monthcomp . ')';
                $sqlwhere.=' OR SUBSTRING(' . $field . ', ' . (strlen($reg[1]) + 1) . ', ' . strlen($reg[2]) . ') >= ' . $yearcomp1 . ' )';
            }
            else {
                $sqlwhere.=') )';
            }
        }
        //print $sqlwhere;
        //print "masktri=".$masktri." maskcounter=".$maskcounter." maskraz=".$maskraz." maskoffset=".$maskoffset." yearcomp=".$yearcomp."<br>\n";
        // Define $sqlstring
        // 
        //TODO en caso de productos no funciona bien (toma los ultimos 4 digitos)
        $posnumstart = strpos($maskwithnocode, $maskcounter); // Pos of counter in final string (from 0 to ...)
        if ($posnumstart < 0)
            return 'ErrorBadMaskFailedToLocatePosOfSequence';
        $sqlstring = 'SUBSTRING(' . 'q.'.$field . ', ' . ($posnumstart + 1) . ', ' . strlen($maskcounter) . ')';
        //print "x".$sqlstring;
        // Define $maskLike
        $maskLike = trim($mask);
        $maskLike = str_replace("%", "_", $maskLike);
        // Replace protected special codes with matching number of _ as wild card caracter
        $maskLike = preg_replace('/\{yyyy\}/i', '____', $maskLike);
        $maskLike = preg_replace('/\{CAT\}/i', $cat, $maskLike);
        $maskLike = preg_replace('/\{ZONE\}/i', $zone, $maskLike);
        $maskLike = preg_replace('/\{yy\}/i', '__', $maskLike);
        $maskLike = preg_replace('/\{y\}/i', '_', $maskLike);
        $maskLike = preg_replace('/\{mm\}/i', '__', $maskLike);
        $maskLike = preg_replace('/\{dd\}/i', '__', $maskLike);
        $maskLike = str_replace($this->dol_string_nospecial('{' . $masktri . '}'), str_pad("", strlen($maskcounter), "_"), $maskLike);
        var_dump($maskLike);
        if ($maskrefclient)
            $maskLike = str_replace($this->dol_string_nospecial('{' . $maskrefclient . '}'), str_pad("", strlen($maskrefclient), "_"), $maskLike);
        //if ($masktype) $maskLike = str_replace($this->dol_string_nospecial('{'.$masktype.'}'),str_pad("",strlen($masktype),"_"),$maskLike);
        if ($masktype)
            $maskLike = str_replace($this->dol_string_nospecial('{' . $masktype . '}'), $masktype_value, $maskLike);

        // Get counter in database
        $counter = 0;
        $qb->select('MAX('.$sqlstring.') as v')
                //->setParameter('1', $sqlstring)
                ;
        $qb->where($qb->expr()->like('q.'.$field, "'".$maskLike."'"));
        $qb->andWhere($qb->expr()->notLike('q.'.$field, "'%PROV%'"));
        $sql = "SELECT MAX(" . $sqlstring . ") as val";
        $sql.= " FROM " . $table;
        //		$sql.= " WHERE ".$field." not like '(%'";
//        $sql.= " WHERE " . Container::getDB()->like($field, '' . $maskLike . '') . "";
//        $sql.= " AND " . Container::getDB()->like($field . ' NOT ', '%PROV%') . "";
//        if (isset($sqlwhere))
//            $sql.=' AND ' . $sqlwhere;
        var_dump($sqlstring);
        var_dump($qb->getQuery()->getSQL());
        $result = $qb->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        echo "Result";
        var_dump($result);
        if ($result) {
            $counter = $result['v'];
        }
        if (empty($counter) || preg_match('/[^0-9]/i', $counter))
            $counter = $maskoffset;

        if ($mode == 'last') {
            
        }
        else if ($mode == 'next') {
            $counter++;
        }
            // Build numFinal
            $numFinal = $mask;

            // We replace special codes except refclient
            $numFinal = preg_replace('/\{yyyy\}/i', date("Y", $date), $numFinal);
            $numFinal = preg_replace('/\{CAT\}/i', $cat, $numFinal);

            $numFinal = preg_replace('/\{ZONE\}/i', $zone, $numFinal);
            $numFinal = preg_replace('/\{yy\}/i', date("y", $date), $numFinal);
            $numFinal = preg_replace('/\{y\}/i', substr(date("y", $date), 2, 1), $numFinal);
            $numFinal = preg_replace('/\{mm\}/i', date("m", $date), $numFinal);
            $numFinal = preg_replace('/\{dd\}/i', date("d", $date), $numFinal);

            // Now we replace the counter
            $maskbefore = '{' . $masktri . '}';
            $maskafter = str_pad($counter, strlen($maskcounter), "0", STR_PAD_LEFT);
            //print 'x'.$maskbefore.'-'.$maskafter.'y';
            $numFinal = str_replace($maskbefore, $maskafter, $numFinal);

            // Now we replace the refclient
            if ($maskrefclient) {
                //print "maskrefclient=".$maskrefclient." maskwithonlyymcode=".$maskwithonlyymcode." maskwithnocode=".$maskwithnocode."\n<br>";
                $maskrefclient_maskbefore = '{' . $maskrefclient . '}';
                $maskrefclient_maskafter = $maskrefclient_clientcode . str_pad($maskrefclient_counter, strlen($maskrefclient_maskcounter), "0", STR_PAD_LEFT);
                $numFinal = str_replace($maskrefclient_maskbefore, $maskrefclient_maskafter, $numFinal);
            }

            // Now we replace the type
            if ($masktype) {
                $masktype_maskbefore = '{' . $masktype . '}';
                $masktype_maskafter = $masktype_value;
                $numFinal = str_replace($masktype_maskbefore, $masktype_maskafter, $numFinal);
            }
        return $numFinal;
    }
    
    /**
     * Shortcut to return the Doctrine Registry service.
     *
     * @return \Doctrine\Bundle\DoctrineBundle\Registry
     *
     * @throws \LogicException If DoctrineBundle is not available
     */
    public function getDoctrine()
    {
        if (!$this->container->has('doctrine')) {
            throw new \LogicException('The DoctrineBundle is not registered in your application.');
        }

        return $this->container->get('doctrine');
    }

    public function setContainer(\Symfony\Component\DependencyInjection\ContainerInterface $container = null) {
         $this->container = $container;
    }
    
    /**
     * 
     * @param type $alias
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilder($alias) {
         return $this->getDoctrine()->getManager()->createQueryBuilder($alias);
    }
    
    function dol_string_nospecial($str,$newstr='_',$badchars='')
    {
            $forbidden_chars_to_replace=array(" ","'","/","\\",":","*","?","\"","<",">","|","[","]",",",";","=");
            $forbidden_chars_to_remove=array();
            if (is_array($badchars)) $forbidden_chars_to_replace=$badchars;
            //$forbidden_chars_to_remove=array("(",")");

            return str_replace($forbidden_chars_to_replace,$newstr,str_replace($forbidden_chars_to_remove,"",$str));
    }
}