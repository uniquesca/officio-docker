<?php
namespace Phpdocx\Elements;
/**
 * Create table of figures
 *
 * @category   Phpdocx
 * @package    elements
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class CreateTableFigures extends CreateElement
{
    /**
     *
     * @var string
     * @access protected
     */
    protected $_xml;

    /**
     *
     * @var CreateTableFigures
     * @access protected
     * @static
     */
    private static $_instance = NULL;

    /**
     * Construct
     *
     * @access public
     */
    public function __construct()
    {
        
    }

    /**
     * Destruct
     *
     * @access public
     */
    public function __destruct()
    {
        
    }

    /**
     * Magic method, returns current XML
     *
     * @access public
     * @return string Return current XML
     */
    public function __toString()
    {
        return $this->_xml;
    }

    /**
     *
     * @return CreateTableFigures
     * @access public
     * @static
     */
    public static function getInstance()
    {
        if (self::$_instance == NULL) {
            self::$_instance = new CreateTableFigures();
        }
        return self::$_instance;
    }

    /**
     * Create table of figures
     *
     * @param string $font
     * @access public
     */
    public function createTableFigures($options, $legendData)
    {
        $this->_xml ='<w:p>';
        if (isset($options['style'])) {
            $this->_xml .= '
                <w:pPr>
                    <w:pStyle w:val="'.$options['style'].'"/>
                    <w:tabs>
                        <w:tab w:leader="dot" w:pos="8494" w:val="right"/>
                    </w:tabs>
                    <w:rPr>
                        <w:noProof/>
                    </w:rPr>
                </w:pPr>
            ';
        }
        $this->_xml .= '<w:fldSimple w:instr=" TOC \h \z \c &quot;'.$options['scope'].'&quot; ">
                '.$legendData->inlineWordML().'
            </w:fldSimple>
        </w:p>';
    }

}
