-- -------------------------------------------------------------------------------------------------------------------------
-- ID: 1
-- RELEASE: CA v2
-- PROBLEM: Executed migrations are not worth maintaining, therefore they are being archived
-- SOLUTION: Delete migrations from DB table, which are already archived
-- INSTRUCTIONS: Run query in the Backend DB
-- EXECUTED: 2022-10-07
-- -------------------------------------------------------------------------------------------------------------------------
DELETE
FROM phinx_log
WHERE version IN (
                  20160111131954,
                  20160513121235,
                  20160919124932,
                  20160919125449,
                  20160919150001,
                  20160919150002,
                  20160919150003,
                  20160919150004,
                  20160919150005,
                  20160919150006,
                  20160919150007,
                  20160919150008,
                  20160919150009,
                  20160919150010,
                  20161229152012,
                  20170110172315,
                  20170111125010,
                  20170117170001,
                  20170125105502,
                  20170127120001,
                  20170131140001,
                  20170201114455,
                  20170203122201,
                  20170203160001,
                  20170209113501,
                  20170209113502,
                  20170209113503,
                  20170215150001,
                  20170215150002,
                  20170217150001,
                  20170223130523,
                  20170227100001,
                  20170303120000,
                  20170306122502,
                  20170314182500,
                  20170321140000,
                  20170321164810,
                  20170322123837,
                  20170324150000,
                  20170413153811,
                  20170420125010,
                  20170515100617,
                  20170526122300,
                  20170726094105,
                  20170823144900,
                  20171103120000,
                  20171109120000,
                  20171116120000,
                  20171116174215,
                  20171127120000,
                  20171129172606,
                  20171130120000,
                  20171201150000,
                  20171201181010,
                  20171207100000,
                  20171207100001,
                  20180125181201,
                  20171211100000,
                  20180125183250,
                  20180410141122,
                  20180420152500,
                  20180420152900,
                  20180420153000,
                  20180420170000,
                  20180517165000,
                  20180707154532,
                  20181001141414,
                  20181017171717,
                  20181214141414,
                  20190216161616,
                  20191023100000,
                  20191025150013,
                  20191111134652,
                  20200511111111,
                  20200902152033,
                  20201112131415,
                  20210108151515,
                  20210129151515,
                  20210514171717,
                  20210621121212,
                  20220630151616,
                  20220630151617,
                  20220708111111,
                  20050101140000,
                  20050101140001,
                  20050101150001,
                  20050101150002,
                  20050101150003,
                  20050101150004,
                  20050101150005,
                  20050101150007,
                  20050101150008,
                  20050101150009,
                  20050101150010,
                  20050101150011,
                  20050101150013,
                  20050101150014,
                  20050101150015,
                  20050101150016,
                  20050101150017,
                  20050101150018,
                  20050101150019,
                  20050101150021,
                  20050101150022,
                  20050101150023,
                  20050101150024,
                  20050101150025,
                  20050101150026,
                  20050101150027,
                  20050101150028,
                  20050101150029,
                  20050101150030,
                  20050101150031,
                  20050101150032,
                  20050101150033,
                  20050101160001,
                  20050101160002,
                  20050101160003,
                  20050101160004,
                  20050101160005,
                  20050101160006,
                  20151229151811,
                  20160118121213,
                  20160126121235,
                  20160210152511,
                  20160215161236,
                  20160217125130,
                  20160219153130,
                  20160225104837,
                  20160303155202,
                  20160310161245,
                  20160322113017,
                  20160322113028,
                  20160325083134,
                  20160405162107,
                  20160516124632,
                  20160520145621,
                  20160616170523,
                  20160915124932,
                  20161003564430,
                  20161005436539,
                  20161014150214,
                  20161019172301,
                  20161116162105,
                  20161122122047,
                  20161201130216,
                  20161206125501,
                  20161221180601,
                  20161221180602,
                  20161221180603,
                  20161221180604,
                  20161221180605,
                  20170116151707,
                  20170123164834,
                  20170209121419,
                  20170214161505,
                  20170227121834,
                  20170302171157,
                  20170307145011,
                  20170309124326,
                  20170309153042,
                  20170331124015,
                  20170403154000,
                  20170403173819,
                  20170403181532,
                  20170511093615,
                  20170526161525,
                  20170606124105,
                  20170609131741,
                  20170612030000,
                  20170612180009,
                  20170613130441,
                  20170613150000,
                  20170615160000,
                  20170621150001,
                  20170622120000,
                  20170623113915,
                  20170623140001,
                  20170704124000,
                  20170706115000,
                  20170707070707,
                  20170711113610,
                  20170711161302,
                  20170713121500,
                  20170713141920,
                  20170719172700,
                  20170801113420,
                  20170801160611,
                  20170807160230,
                  20170808153535,
                  20170810153737,
                  20170811133838,
                  20170811171707,
                  20170818133208,
                  20170818141930,
                  20170831120000,
                  20170901164801,
                  20170901171203,
                  20170907115802,
                  20170918120456,
                  20170929115332,
                  20170929150000,
                  20170929171035,
                  20171010132701,
                  20171018121212,
                  20171103083910,
                  20171129160451,
                  20171206165605,
                  20171222150111,
                  20180206164550,
                  20180207160430,
                  20180220160430,
                  20180410120000,
                  20180411150000,
                  20180411150001,
                  20180423121212,
                  20180428175154,
                  20180502181930,
                  20180505050505,
                  20180508080808,
                  20180511111111,
                  20180511111112,
                  20180511111113,
                  20180511111114,
                  20180511111115,
                  20180511111116,
                  20180514141401,
                  20180514141402,
                  20180514141403,
                  20180514141404,
                  20180514141405,
                  20180604153007,
                  20180613115000,
                  20180618151515,
                  20180619134302,
                  20180626171954,
                  20180628170000,
                  20180628180000,
                  20180706121212,
                  20181026121314,
                  20181120202020,
                  20181126122820,
                  20181128171205,
                  20181206170503,
                  20181224170707,
                  20190108160000,
                  20190109103000,
                  20190111115400,
                  20190114113500,
                  20190116112300,
                  20190116161616,
                  20190212121200,
                  20190417171717,
                  20190423232323,
                  20190425121212,
                  20190506125700,
                  20190605101010,
                  20190612154433,
                  20190712162020,
                  20190903152300,
                  20190910101010,
                  20190923232323,
                  20191007135948,
                  20191011161243,
                  20191016185518,
                  20191018185518,
                  20191023181818,
                  20191025151515,
                  20191028134444,
                  20191112121212,
                  20191206161616,
                  20191210101010,
                  20191211131953,
                  20200206224602,
                  20200215124932,
                  20200227121212,
                  20200302121212,
                  20200330150000,
                  20200406161616,
                  20200407111111,
                  20200415171717,
                  20200425185630,
                  20200504152323,
                  20200512202002,
                  20200521141413,
                  20200521141414,
                  20200526151515,
                  20200529181818,
                  20200604160000,
                  20200611120000,
                  20200617161514,
                  20200713173922,
                  20200714191919,
                  20200715160321,
                  20201007070707,
                  20201015101010,
                  20201119141414,
                  20201120202020,
                  20201222122200,
                  20201224202020,
                  20201229181400,
                  20201229191500,
                  20210118181818,
                  20210121141414,
                  20210121151515,
                  20210128212121,
                  20210128212122,
                  20210201121221,
                  20210219191919,
                  20210219191920,
                  20210219191921,
                  20210223232323,
                  20210322154300,
                  20210326121212,
                  20210413151515,
                  20210414181818,
                  20210415151515,
                  20210503140001,
                  20210514135131,
                  20210517171717,
                  20210519130909,
                  20210531151515,
                  20210608080808,
                  20210625151515,
                  20210709141414,
                  20210721212121,
                  20210729121212,
                  20210831173915,
                  20210913161616,
                  20210917171717,
                  20210917171718,
                  20210920212515,
                  20210922222222,
                  20210929171717,
                  20211001010101,
                  20211008194134,
                  20211019191919,
                  20211027171717,
                  20211102171717,
                  20211103121212,
                  20211108130338,
                  20211109090909,
                  20211116161616,
                  20211116171717,
                  20211207070707,
                  20211208080808,
                  20211220155131,
                  20220106123531,
                  20220120151515,
                  20220120151516,
                  20220124181818,
                  20220204040404,
                  20220216161616,
                  20220222222222,
                  20220309090909,
                  20220311111111,
                  20220316161616,
                  20220318181818,
                  20220401101255,
                  20220408080808,
                  20220413122309,
                  20220414141414,
                  20220502171717,
                  20220512121212,
                  20220519191919,
                  20220526181818,
                  20220610110355,
                  20220615120034,
                  20220628151515,
                  20220728141414,
                  20220804040404,
                  20220816161616,
                  20220818181818,
                  20220824161616,
                  20220824181818,
                  20220912121212,
                  20220916161616,
                  20220926110355,
                  20220926141414,
                  20220929151515,
                  20220930202020,
                  20220930202021,
                  20221003181818,
                  20221004151515
    );