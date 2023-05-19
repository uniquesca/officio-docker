<?php

namespace Officio;

/**
 * Pagination Class, used for various type of paging.
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class PagingManager
{

    // Possible view cases
    public const DIGG_PAGING = "DP";
    public const ALL_123 = "all";
    public const ALL_RANGE_123 = "123range";
    public const SERIES_123 = "123series";
    public const SERIES_RANGE_123 = "123srange";

    private $base_url; // The page we are linking to
    private $pgparam;
    private $total_records = ''; // Total number of items (database results)
    private $per_page = 10; // Max number of items you want shown per page
    private $start = 0;
    private $cur_page = 0; // The current page being viewed
    private $pagingStyle; // paging style
    private $pagingStr = ''; //

    private $getStr = '';

    // for 123_PAGING
    private $seriesRange = 5; // Number of "digit" links to show before/after the currently viewed page

    // for DIGG Style PAGING
    private $adjacents = 3; // How many adjacent pages should be shown on each side?

    /**
     * PagingManager constructor.
     * @param $baseUrl
     * @param int $pagingSize
     * @param string $pagingStyle
     * @param string $key
     */
    public function __construct($baseUrl, $pagingSize = 10, $pagingStyle = self::DIGG_PAGING, $key = 'page')
    {
        $this->pgparam  = $key;
        $this->base_url = $baseUrl;
        $this->initialize($pagingSize);
        $this->pagingStyle = $pagingStyle;
    }

    // --------------------------------------------------------------------

    /**
     * Initialize Preferences
     *
     * @access    private
     * @param int $pagingSize initialization parameters
     * @return    void
     */
    private function initialize(int $pagingSize)
    {
        if ((isset($_GET)) && (is_array($_GET))) {
            $getStr = '';
            foreach ($_GET as $key => $value) {
                if (strstr($key, $this->pgparam)) {
                    continue;
                }
                $getStr .= "$key=$value&amp;";
            }


            $this->getStr = ($getStr == '') ? "?" : '?' . $getStr;

            $this->cur_page = (isset($_GET[$this->pgparam])) ? $_GET[$this->pgparam] : 1;
            // make sure that pagenumber is integer
            if (!is_numeric($this->cur_page) or ($this->cur_page == 0)) {
                $this->cur_page = 1;
            }
        } else {
            $this->cur_page = 1;
        }

        $this->per_page = $pagingSize;
    }

    public function getStart()
    {
        if ($this->cur_page > 0) {
            //first item to display on this page
            $this->start = ($this->cur_page - 1) * $this->per_page;
        } else {
            //if no page var is given, set start to 0
            $this->start = 0;
        }
        return $this->start;
    }

    public function getOffset()
    {
        return $this->per_page;
    }

    public function doPaging($totalRecords)
    {
        if ($this->pagingStr != '') {
            return $this->pagingStr;
        }
        $this->total_records = $totalRecords;

        // If our item count or per-page total is zero there is no need to continue.
        if ($this->total_records == 0 or $this->per_page == 0) {
            return '';
        }

        if ($this->pagingStyle == self::DIGG_PAGING) {
            $this->pagingStr = $this->digg_paging();
        } elseif ($this->pagingStyle == self::ALL_123) {
            $this->pagingStr = $this->printPageNumbers();
        } elseif ($this->pagingStyle == self::ALL_RANGE_123) {
            $this->pagingStr = $this->printPageNumbers('all', 'fromto');
        } elseif ($this->pagingStyle == self::SERIES_123) {
            $this->pagingStr = $this->printPageNumbers('series');
        } elseif ($this->pagingStyle == self::SERIES_RANGE_123) {
            $this->pagingStr = $this->printPageNumbers('series', 'fromto');
        }

        return $this->pagingStr;
    }


    // --------------------------------------------------------------------

    public function digg_paging()
    {
        //previous page is page - 1
        $prev = $this->cur_page - 1;
        //next page is page + 1
        $next = $this->cur_page + 1;
        //lastpage is = total pages / items per page, rounded up.
        $lastpage = ceil($this->total_records / $this->per_page);

        //last page minus 1
        $lpm1 = $lastpage - 1;

        $pagination = "";

        if ($lastpage > 1) {
            $pagination .= "<div class=\"pagination\">";
            //previous button
            if ($this->cur_page > 1) {
                $pagination .= "<a href=\" $this->base_url$this->getStr$this->pgparam=$prev\">&lt;&lt; Previous</a>";
            } else {
                $pagination .= "<span class=\"disabled\">&lt;&lt; Previous</span>";
            }
            //pages

            if ($lastpage <= 5 + ($this->adjacents * 2))    //not enough pages to bother breaking it up
            {
                for ($counter = 1; $counter <= $lastpage; $counter++) {
                    if ($counter == $this->cur_page) {
                        $pagination .= "<span class=\"current\">$counter</span>";
                    } else {
                        $pagination .= "<a href=\"$this->getStr$this->pgparam=$counter\">$counter</a>";
                    }
                }
            } elseif ($lastpage > 5 + ($this->adjacents * 2))    //enough pages to hide some
            {
                //close to beginning; only hide later pages
                if ($this->cur_page < 1 + ($this->adjacents * 2)) {
                    for ($counter = 1; $counter < 4 + ($this->adjacents * 2); $counter++) {
                        if ($counter == $this->cur_page) {
                            $pagination .= "<span class=\"current\">$counter</span>";
                        } else {
                            $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$counter\">$counter</a>";
                        }
                    }
                    $pagination .= "...";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$lpm1\">$lpm1</a>";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$lastpage \">$lastpage</a>";
                } //in middle; hide some front and some back
                elseif ($lastpage - ($this->adjacents * 2) > $this->cur_page && $this->cur_page > ($this->adjacents * 2)) {
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=1 \">1</a>";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=2 \">2</a>";
                    $pagination .= "...";
                    for ($counter = $this->cur_page - $this->adjacents; $counter <= $this->cur_page + $this->adjacents; $counter++) {
                        if ($counter == $this->cur_page) {
                            $pagination .= "<span class=\"current\">$counter</span>";
                        } else {
                            $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$counter\">$counter</a>";
                        }
                    }

                    $pagination .= "...";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$lpm1\">$lpm1</a>";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$lastpage\">$lastpage</a>";
                } //close to end; only hide early pages
                else {
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=1\">1</a>";
                    $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=2\">2</a>";
                    $pagination .= "...";
                    for ($counter = $lastpage - (2 + ($this->adjacents * 2)); $counter <= $lastpage; $counter++) {
                        if ($counter == $this->cur_page) {
                            $pagination .= "<span class=\"current\">$counter</span>";
                        } else {
                            $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$counter\">$counter</a>";
                        }
                    }
                }
            }

            //next button
            if ($this->cur_page < $counter - 1) {
                $pagination .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$next\">Next &gt;&gt;</a>";
            } else {
                $pagination .= "<span class=\"disabled\">Next &gt;&gt;</span>";
            }
            $pagination .= "</div>\n";
        }

        return $pagination;
    }

    private function printPageNumbers($range = 'all', $type = 'numbers')
    {
        // find the total number of pages.
        $totalPages = ceil($this->total_records / $this->per_page);

        $paging = '';

        switch ($range) {
            case 'all':
                for ($i = 1; $i <= $totalPages; $i++) {
                    switch ($type) {
                        case 'numbers':
                            if ($i != $this->cur_page) {
                                $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$i\">$i</a> ";
                            } else {
                                $paging .= "<span class=\"current\">$i</span>";
                            }
                            break;

                        case 'fromto':
                            $from = $this->per_page * ($i - 1) + 1;
                            $to   = $from + $this->per_page - 1;

                            if ($to > $this->total_records) {
                                $to = $this->total_records;
                            }

                            if ($i != $this->cur_page) {
                                $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$i\">$from-$to</a> ";
                            } else {
                                $paging .= "<span class=\"current\">$from-$to</span> ";
                            }
                            break;
                    } // end of switch statement
                }
                break; // end of case all

            case 'series':
                /*
                In ideal case, there should be seriesrange numbers on left side of selected page and seriesrange-1
                pages on right side of selected page. However, in some cases like for page number less than
                seriesrange and last pages this calculation doesn't work. e.g for page No. 2 there can be only [1] on
                left hand side of the selected page i.e 2, so the below calculation of seriesrange1 and seriesrange2 is
                for adjusting the remaining numbers so that ultimately the total page number printed on screen should be
                seriesrange * 2 */
                if ($this->cur_page < $this->seriesRange) {
                    $seriesrange1 = ($this->seriesRange - $this->cur_page) + $this->seriesRange;
                } else {
                    $seriesrange1 = $this->seriesRange;
                }

                if (($totalPages - $this->cur_page) < $this->seriesRange) {
                    $seriesrange2 = ($this->seriesRange - 1 - ($totalPages - $this->cur_page)) + $this->seriesRange;
                } else {
                    $seriesrange2 = $this->seriesRange;
                }

                $from = ($this->cur_page - $seriesrange2 < 1) ? 1 : $this->cur_page - $seriesrange2;
                $to   = ($this->cur_page + $seriesrange1 > $totalPages) ? $totalPages : $this->cur_page + $seriesrange1;

                if (($to - $from) >= ($this->seriesRange * 2)) {
                    // make sure that the total page number printed at any time is seriesrnage*2
                    if ($seriesrange2 > $seriesrange1) {
                        $from--;
                    } else {
                        $to--;
                    }
                }

                $prev = ($this->cur_page > 1) ? $this->cur_page - 1 : 1;
                $next = ($this->cur_page < $totalPages) ? $this->cur_page + 1 : $totalPages;
                if ($this->cur_page == 1) {
                    $paging .= "<span class=\"disabled\">&lt;&lt;</span>";
                    $paging .= "<span class=\"disabled\">Previous</span> ";
                } else {
                    // Print last and previous page number links
                    $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=1\" title=\"First Page\">&lt;&lt;</a> ";
                    $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$prev\" title=\"Previous Page\">Previous</a> ";
                }

                for ($i = $from; $i <= $to; $i++) {
                    switch ($type) {
                        case 'numbers':
                            if ($i != $this->cur_page) {
                                $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$i\">$i</a> ";
                            } else {
                                $paging .= "<span class=\"current\">$i</span>";
                            }
                            break;

                        case 'fromto':

                            $pageFrom = $this->per_page * ($i - 1) + 1;
                            $pageTo   = $pageFrom + $this->per_page - 1;
                            if ($pageTo > $this->total_records) {
                                $pageTo = $this->total_records;
                            }
                            if ($i != $this->cur_page) {
                                $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$i\">$pageFrom-$pageTo</a> ";
                            } else {
                                $paging .= "<span class=\"current\">$pageFrom-$pageTo</span> ";
                            }
                            break;
                    }
                }

                if ($this->cur_page == $totalPages) {
                    $paging .= "<span class=\"disabled\">Next </span>";
                    $paging .= "<span class=\"disabled\">&gt;&gt;</span> ";
                } else {
                    $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$next\" title=\"Next Page\">Next</a>";
                    $paging .= "<a href=\"$this->base_url$this->getStr$this->pgparam=$totalPages\" title=\"Last Page\">&gt;&gt;</a> ";
                }
                break;
        } // End of main switch statement.

        return "<div class=\"pagination\">" . $paging . "</div>";
    }

    public function getPagingString($str = '')
    {
        if ($str == '') {
            $str = "Showing %d to %d of %d records";
        }

        $pageFrom = $this->per_page * ($this->cur_page - 1) + 1;
        $pageTo   = $pageFrom + $this->per_page - 1;
        if ($pageTo > $this->total_records) {
            $pageTo = $this->total_records;
        }

        return sprintf($str, $pageFrom, $pageTo, $this->total_records);
    }


}
// END Pagination Class
