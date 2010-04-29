<?php

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

header("Cache-Control: no-cache");

// list from:
// http://www.nsdatabase.co.uk/newspaperResults.CFM?newspapers__name=

$newspapers = <<<EOT
Aberdeen Citizen
Aberdeen Evening Express
Abergavenny Chronicle
Accrington Observer
Adlington & Blackrod Courier
Advertiser - Barrow
Airdrie & Coatbridge Advertiser
Aldershot News & Mail Series
Alfreton Chad
Allanwater News
Alloa & Hillfoots Advertiser Journal
Alloa & Hillfoots Wee County News
Andersonstown News
Andersonstown News (Mon)
Andover Advertiser
Andover Advertiser Midweek
Annandale Series
Arbroath Herald
Armagh Down Observer
Armagh Observer
Arran Banner
Ashbourne News Telegraph
Ashby Coalville & Swadlincote Times
Ashford Extra
Ashton Under Lyne Reporter Group
Asian News
Ayr Advertiser
Ayrshire Post Series
Ayrshire World Group
Ballymena & Antrim Times
Ballymena Chronicle
Ballymena Guardian
Banbridge Chronicle
Banbury & Bicester Why
Banbury Cake
Banbury Guardian
Banbury Review
Banffshire Advertiser
Banffshire Herald
Banffshire Journal
Bangor Mail (ch Series)
Barking & Dagenham Post
Barking & Dagenham Yellow Advertiser
Barnet Hendon Press
Barnsley Chronicle
Barnsley Independent
Barrow - North West Evening Mail
Barry & District News
Basildon & Southend Echo
Basildon Recorder
Basildon Yellow Advertiser
Basingstoke Extra
Basingstoke Monday Gazette
Basingstoke Observer
Basingstoke Thursday Weekend Gazette
Bath Chronicle
Bearsden Milngavie & Glasgow Extra
Bedford Times & Citizen Group
Bedfordshire on Sunday
Belfast - Sunday Life
Belfast News
Belfast Telegraph
Belper News
Berrow's Worcester Journal
Berwick Advertiser
Berwickshire News East Lothian Herald
Beverley Guardian
Bexhill Adnews
Bexhill Observer
Bexley & Bromley Times (excl. 4550 bulk copies)
Bexley Borough Property News
Bexley Mercury
Bexley News Shopper Series
Bicester Advertiser
Bicester Review
Biggleswade Advertiser
Biggleswade Chronicle
Birmingham Independent
Birmingham Mail
Birmingham Mail Extra
Birmingham Post
Birmingham Southwest Why
Black Country Bugle
Blackburn Citizen Series
Blackmore Vale Magazine
Blackpool Reporter
Blairgowrie Advertiser
Bolsover Advertiser
Bolton Journal
Bolton News
Bootle Times - Mediamix
Border Telegraph
Boston Standard Group
Bournemouth - The Daily Echo
Bournemouth Advertiser
Bracknell & Wokingham Midweek
Bracknell & Wokingham News
Bracknell & Wokingham Standard
Bradford - Telegraph & Argus
Braintree & Witham Times
Braintree & Witham Weekly News
Bramley Armley & Wortley Advertiser
Brechin Advertiser
Brecon & Radnor Express
Brentwood Gazette
Brentwood Weekly News
Brentwood Yellow Advertiser
Bridgnorth Journal
Bridgwater Mercury
Bridgwater Times
Bridlington Free Press
Bridlington Gazette & Herald
Bridport & Lyme News
Brighouse Echo
Brighton & Hove Leader
Bristol Evening Post
Bristol Observer Group
Bromley News Shopper Series
Bromley Property News
Bromley, Biggin Hill, Oxted & Caterham News Series
Bromsgrove & Droitwich Standard
Bromsgrove Droitwich Advertiser
Buchan Observer Group
Buckingham & Winslow Advertiser
Buckinghamshire Advertiser & Examiner Series
Bucks Advertiser
Bucks Free Press
Bucks Free Press - Midweek
Bucks Herald
Burnham & Highbridge Times
Burnham & Highbridge Weekly News
Burnley Citizen Series
Burnley Express (Fri)
Burnley Express (Tue)
Burnley Pendle & Clitheroe Reporter
Burton Advertiser
Burton Mail
Bury Free Press
Bury Journal
Bury St Edmunds Citizen
Bury Times
Buxton Advertiser
Buxton Times
Buy Sell Mid Cheshire & Chester
Buy Sell S.Cheshire & N.Shropshire
Caernarfon Herald (ch Series)
Caithness Courier
Calderdale News
Cambrian News
Cambridge Evening News
Cambridge News & Crier
Cambridgeshire Times & Standard Group
Camden New Journal
Campaign Blackwood/Newbridge
Campaign Caerphilly Ystrad Mynach & Bargoed
Campbeltown Courier & Argyllshire Advertiser
Cannock & Lichfield Chronicle
Cannock Chase & Burntwood Post
Canterbury Adscene - KRN
Canterbury Extra
Canvey Island & Benfleet Times
Cardigan & Tivy-Side Advertiser
Carmarthen Journal
Carrick Gazette & Girvan News
Castlepoint Rayleigh Standard
Castlepoint Yellow Advertiser
Catholic Times
Central Fife Times & Advertiser
Chard & Ilminster News
Cheadle Post & Times
Cheadle Times Uttoxeter Echo
Chelmsford & Maldon Yellow Advertiser
Chelmsford Weekly News
Cheltenham Why
Chester & District Standard
Chester Chronicle
Chesterfield Advertiser
Chesterfield Express
Chichester & Bognor Journal & Guardian Group
Chichester Observer Series
Chorley & Leyland Guardian
Chorley Citizen
Christchurch Advertiser
Chronicle Xtra - Chester
Citizen First (Milton Keynes)
City AM
City Of London & Docklands Times
Clacton Frinton & Walton Gazette
Cleethorpes Chronicle
Clevedon Mercury
Clifton Chronicle
Clitheroe Advertiser & Times
Clyde Weekly News
Clydebank Post
Colchester Gazette
Colchester Weekly News
Coleraine Chronicle
Coleraine Leader
Coleraine Times
Community Telegraph East Belfast
Community Telegraph North & Newtownabbey
Community Telegraph North Down
Community Telegraph South Belfast
Congleton Chronicle Series
Cork Evening Echo
Cornish & Devon Post Series
Cornish Guardian
Cornish Times
County Down Outlook
County Down Spectator Group
County Echo
Courier & Advertiser - Dundee
Courier Midweek Leamington
Coventry North Why
Coventry Observer
Coventry South Why
Coventry Telegraph
Coventry Times
Craigavon Echo
Craven Herald & Pioneer
Crawley News
Crawley Observer
Crawley Weekend Herald
Crewe & Nantwich Guardian Series
Crewe Chronicle
Crewe Mail
Crosby Herald - Mediamix
Croydon & Sutton Post
Croydon Advertiser Series
Croydon Guardian
Cumberland & Westmorland Herald
Cumbernauld Advertiser
Cumbernauld News & Kilsyth Chronicle
Cumnock Chronicle & Muirkirk Advertiser
Cynon Valley Leader (Aberdare)
Daily Express
Daily Mail
Daily Mirror
Daily Post - Wales
Daily Record - Scotland
Daily Star
Daily Telegraph
Darlington & South Durham Herald & Post
Darlington & Stockton Times
Dartford & Gravesend Messenger
Dartford & Gravesend News Shopper Series
Daventry Express
Daventry Review
Dawlish Gazette & Teignmouth News Group
Dearne Valley Weekender
Deeside/Donside Piper & Inverurie Herald
Denbighshire Free Press
Denbighshire Visitor
Derby Evening Telegraph
Derby Express Series
Derbyshire Times
Dereham & Fakenham Times
Derry Journal (Fri)
Derry Journal (Tue)
Derry News
Dewsbury Reporter Group
Dinnington & Maltby Trader
Diss Express
Diss Wymondham & Attleborough Mercury
Docklands
Docklands News
Doncaster Advertiser Series
Doncaster Free Press
Donegal News
Dorset Echo
Down Democrat
Down Recorder
Downs Mail
Driffield Times
Dromore & Banbridge Leader
Dronfield Advertiser
Dudley Chronicle
Dudley News
Dumbarton Vale of Leven Reporter
Dumfries & Galloway Standard (Fri)
Dumfries & Galloway Standard (Wed)
Dumfries & Galloway Today
Dumfries Courier
Dundee Evening Telegraph
Dunfermline Press & West of Fife Advertiser
Dungannon News & Tyrone Courier
Dungannon Observer
Dunoon Observer & Argyllshire Standard
Durham Times
Ealing Gazette Series
Ealing Informer
Ealing Leader
Easingwold Advertiser & Weekly News
East Anglian Daily Times
East Antrim Gazette Group
East Antrim Times
East Cleveland Herald & Post
East Cumbrian Gazette
East Fife Mail
East Grinstead Courier & Observer
East Herts Herald
East Kent Gazette Group - KRN
East Kent Mercury
East Kilbride Mail
East Kilbride News
East London Advertiser
East Lothian Courier
East Lothian News Group
Eastbourne Advertiser
Eastbourne Gazette
Eastbourne Herald
Eastleigh News Extra & Advertiser
Eastside News
Eastwood & Kimberley Advertiser
Eckington Leader
Edinburgh - Evening News
Ellesmere Port Pioneer
Ellesmere Port Standard
Ellon Advertiser
Elmbridge Guardian
Ely Weekly News
Enfield Advertiser
Enfield Gazette Series
Enfield Independent
Epping Forest Guardian Series
Epping Forest Independent
Epworth Bells
Esher News & Mail Series
Eskdale & Liddesdale Advertiser
Essex Chronicle
Essex County Standard
Essex Enquirer
Essex Recruitment Express
Evening Leader - Wrexham & Chester
Evesham & Cotswold Journal
Evesham & Cotswold Why
Evesham & Cotswolds Observer
Exeter Express & Echo
Exeter Times
Exmouth Herald
Exmouth Journal
Falkirk Grangemouth & Linlithgow Advertiser
Falkirk Herald
Farmers Guardian
Farnham Herald Series
Fenland Citizen
Fermanagh Herald
Fermanagh News
Fife & Kinross Extra
Fife Free Press
Fife Herald News & St Andrews Citizen
Fife Leader - North
Fife Leader - South
Filey & Hunmanby Mercury
Financial Times
Fleetwood Weekly News
Flintshire Standard
Folkestone & Dover Adscene - KRN
Folkestone & Dover Extra
Folkestone Herald Dover Express Group - KRN
Forest & Wye Valley Review
Forfar Dispatch & Kirriemuir Herald
Formby Times - Mediamix
Forres Gazette
Fosse Way Magazine
Foyle News
Free Press of Monmouthshire Group
Fulham Chronicle Series
Gainsborough News
Gainsborough Standard
Gainsborough Target
Galloway Gazette & Stranraer News
Galloway News
Galway Advertiser
Garavi Gujarat
Gateshead Chronicle eXTRA
Gatwick Skyport
Gazette & Herald - Yorkshire
Get Reading
Glamorgan Gazette (Bridgend)
Glamorgan Gem
Glasgow - Evening Times
Glasgow South & Eastwood Extra
Glenrothes Gazette
Gloucester & Cheltenham News Series
Gloucestershire County Gazette
Gloucestershire Echo
Gloucestershire Independent Series
Goole Times
Grantham Citizen
Grantham Journal
Gravesend Reporter/Dartford & Swanley Times (excl 3696 bulk)
Great Barr Observer
Great Yarmouth Advertiser
Great Yarmouth Mercury
Greenock Telegraph
Grimsby & Cleethorpe Life
Grimsby Telegraph
Guardian Property (Croydon)
Guardian Property (Mitcham & Morden))
Guardian Property (Sutton & Epsom)
Guernsey Globe
Guernsey Weekly Press
Gwent Gazette
Hackney Gazette
Halesowen Chronicle
Halesowen News
Halesowen Why
Halifax Evening Courier
Halstead Gazette
Hamilton Advertiser
Hamodia
Hampshire Chronicle
Hampshire Observer Series
Hampstead & Highgate Express Group
Hants Advertiser & Times Series
Harborough Mail
Haringey Advertiser
Haringey Independent
Harlow Herald
Harlow Star Series
Harrogate Advertiser Group
Harrogate Herald
Harrow & Wembley Observer
Harrow Informer
Harrow Leader
Harrow Times Series
Hartlepool Mail
Hartlepool Star
Harwich & Manningtree Standard
Hastings & St Leonards Observer
Hastings Adnews
Haverhill Echo
Haverhill Weekly News
Hawick News
Hayling Islander
Heathrow Skyport
Hebden Bridge Times
Helensburgh Advertiser
Helston & District Free Gazette
Hemel Hempstead Gazette
Hemel Hempstead Herald Express
Hemel Hempstead Local TV Guide
Hemsworth & S Elmsall Express
Hendon Times Group
Henley Standard
Herald & Post Edinburgh
Herald & Post Fife
Herald & Post West Lothian
Herald North Group
Herald Series - Oxfordshire
Hereford & Leominster Journal
Hereford Admag
Hereford Times
Herne Bay & Whitstable Times - KRN
Herts & Essex Observer Group
Herts & Lea Valley Star
Herts Advertiser Series
Herts Mercury Series
Hexham Courant
Heywood Advertiser
High Peak Courier
Hinckley Herald & Journal
Hinckley Times
Hitchin Letchworth & Baldock Advertiser
Holderness Gazette
Holyhead Mail (ch Series)
Horncastle News
Horsforth Advertiser
Horsham Advertiser Series
Hounslow Borough Chronicle
Hucknall & Bulwell Dispatch
Huddersfield Daily Examiner
Huddersfield Weekly News
Hull Advertiser Group
Hull Daily Mail
Huntingdon/St Ives & St Neots News & Crier
Huntly Express
Ilford Recorder Series
Ilkeston Advertiser
Ilkley Gazette and Wharfedale & Airdale Observer
Impartial Reporter
Independent on Sunday
Inverclyde Extra
Inverness Courier (Fri)
Inverness Courier (Tue)
Inverness Herald Group
Inverness News Group
Inverurie Advertiser
Ipswich - Evening Star
Irish Examiner
Irish News (Belfast)
Irvine Herald
Isle of Man Courier
Isle of Man Examiner
Isle of Thanet Gazette - KRN
Isle of Wight County Press
Islington Camden Gazette & Journal Series (excl. 5000 bulk)
Islington Tribune
Jersey Evening Post
Jersey Weekly Post
Jewish Chronicle
John O'Groat Journal
Keighley News
Kent & Sussex Courier
Kent Messenger Series
Kent on Saturday
Kent on Sunday
Kentish Express Series
Kentish Gazette Group
Kidderminster Chronicle
Kidderminster Shuttle Series
Kidderminster Why
Kilmarnock Standard
Kincardineshire Observer
Kings Lynn Citizen
Kingston Guardian
Kingston Informer
Kirkintilloch Herald
Kirklees - Reporter Extra
Knutsford Guardian
Lanark & Carluke Gazette
Lanarkshire Extra Group
Lanarkshire World Group
Lancashire & Wigan Evening Post
Lancashire Telegraph
Lancaster & Morecambe Reporter
Lancaster Guardian Series
Larne Times
Launceston Journal Gazette
Leamington Spa Courier Group
Leamington Warwick & Southam Observer
Leeds - Yorkshire Evening Post
Leeds Weekly News Group
Leek Post & Times
Leicester Mail Group
Leicester Mercury
Leigh Journal
Leigh Reporter
Leigh Times
Leighton Buzzard Citizen
Leighton Buzzard News
Leighton Buzzard Observer & Linslade Gazette
Lennox Herald
Lewisham & Greenwich Mercury Group
Lewisham & Greenwich News Shopper Series
Lichfield Mercury Series
Lincoln Target
Lincolnshire Citizen Series
Lincolnshire Echo
Lincolnshire Free Press
Lincolnshire Telegraph
Linlithgowshire Journal & Gazette
Lisburn Echo
Liskeard Gazette & Journal Series
Liverpool Daily Post
Liverpool Echo
Llanelli Star Series
London Evening Standard
London Informer
London Lite
Londonderry Sentinel
Lothian & Peebles Times Group
Loughborough Echo
Louth Leader
Lowestoft & Beccles Journal Group
Ludlow Journal
Ludlow Tenbury Leominster Advertiser
Lurgan & Portadown Examiner
Lurgan Mail
Luton & Dunstable Express
Luton Dunstable Herald & Post Group
Luton News & Dunstable Gazette Group
Lutterworth Advertiser
Lutterworth Observer
Lynn News (Fri)
Lynn News (Tue)
Lytham St Annes Express
Macclesfield & Poynton Times Group
Macclesfield Express
Maidenhead Advertiser
Maidstone Extra
Mail on Sunday
Maldon & Burnham Standard
Malton & Pickering Mercury
Malvern Gaz Ledbury Reporter
Malvern Ledbury & Tewkesbury Why
Manchester Evening News (Mon-Wed)
Manchester Evening News (Sat)
Manchester Evening News (Thu-Fri)
Mansfield & Ashfield Chad
Mansfield & Ashfield Observer
Mansfield & Ashfield Recorder
Manx Independent
Market Rasen Mail
Matlock Mercury & W Derbyshire News
Mearns Leader
Medway & District Adscene - KRN
Medway Extra
Medway Messenger (Mon)
Medway News Series - KRN
Melton Citizen
Melton Times
Merseymart & Star
Merseyside/Lancashire Champion Series
Merthyr Express
Messenger Extra
Methodist Recorder
Metro East Midlands
Metro London
Metro Merseyside
Metro Midlands
Metro North East
Metro North West
Metro Scotland
Metro South Wales
Metro West Country
Metro Yorkshire
Mid Devon Advertiser & Times Group
Mid Devon Gazette Series
Mid Devon Star
Mid Somerset Group
Mid Sussex Citizen
Mid Sussex Leader
Mid Sussex Property Leader
Mid Sussex Times
Mid Ulster Mail Series
Mid Ulster Observer
Middlesbrough Herald & Post
Middleton & North Manchester Guardian
Midlothian Advertiser
Midsomer Norton & Radstock Journal
Midweek Herald - East Devon
Midweek Mercury - Weston Super Mare
Milford Mercury
Milngavie & Bearsden Herald
Milton Keynes Citizen
Mitcham Morden & Wimbledon Post
MK News
Monmouthshire Beacon
Montrose Review
Moorlands Advertiser
Morpeth Herald
Motherwell Times Series
Mourne Observer & Co Down News
Neath & Port Talbot Guardian
Nelson Leader Series
New Forest Post
Newark Advertiser
Newark Trader Pictorial
Newbury & Thatcham Chronicle
Newbury Advertiser
Newbury Weekly News
Newcastle Chronicle eXTRA
Newcastle Evening Chronicle
Newcastle Journal
Newcastle upon Tyne Sunday Sun
Newham Recorder Series
Newmarket Journal
Newmarket Weekly News
Newport & Market Drayton Advertiser
Newquay & St Austell Voice
Newry Democrat
Newry Reporter
News & Star - Carlisle
News Guardian - Whitley Bay
News of the World
Newtownards Chronicle
Norfolk Eastern Daily Press
Normanton Advertiser
North Belfast News
North Devon Gazette
North Devon Journal
North East Exclusive
North East Manchester Advertiser
North Herts Comet Group
North Norfolk News
North Somerset Times
North Staffs Advertiser
North Wales Chronicle Series
North Wales Pioneer
North Wales Weekly News
North West Leics & South Derbys Leader
North Yorkshire Advertiser
North Yorkshire News
Northampton Chronicle & Echo
Northampton Herald & Post
Northampton Mercury & Citizen
Northants Evening Telegraph
Northants Mercury & Citizen Series
Northants on Sunday
Northern Constitution
Northern Scot
Northern Scot Midweek Extra
Northern Times
Northumberland Gazette
Northumberland News Post Leader
Northwich Guardian Group
Norwich Advertiser
Norwich Evening News
Nottingham & Long Eaton Recorder Group
Nottingham & Long Eaton Topper
Nottingham Evening Post
Nuneaton News - Free (Wed)
Nuneaton News - Paid
Nuneaton Weekly Tribune
Oban Times & W Highland Times
Oldham Advertiser
Oldham Chronicle Weekend
Oldham Evening Chronicle
Orkney Today
Ormskirk Advertiser Series - Mediamix
Ossett & Horbury Observer
Oswestry & Border Counties Advertizer
Oxford Journal
Oxford Mail
Oxford Star
Oxford Times
Packet Group
Paisley & Renfrewshire Extra
Paisley & Renfrewshire Gazette Series
Paisley Daily Express
Paisley People
Peak Times
Peeblesshire News
Penarth Times
Penwith Pirate
Perth Shopper
Perthshire Advertiser (Fri)
Perthshire Advertiser (Tue)
Peterborough Citizen
Peterborough Evening Telegraph
Peterlee Star
Petersfield & Bordon Post
Plymouth Extra
Plymouth Sunday Independent
Pocklington Post
Pontefract & Castleford Express
Pontefract & Castleford Extra
Pontypridd Observer Group
Poole Advertiser
Portadown Times
Portsmouth Journal Group
Portsmouth News
Press & Journal - Aberdeen
Preston & Leyland Reporter
Prestwich & Whitefield Guide
Prestwich Advertiser
Property Weekly (Kingston)
Property Weekly (Putney & Wandsworth)
Property Weekly (Wimbledon)
Pudsey Advertiser
Pudsey Times
Pulman's Weekly News Group
Rayleigh Times
Reading Chronicle
Reading Midweek
Reading Post
Redbridge & Ilford Yellow Advertiser
Redditch & Alcester Standard
Redditch & Bromsgrove Why
Redditch Advertiser Alcester Chronicle
Redhill Reigate & Horley Life
Reigate & Epsom Post
Renfrewshire World
Retford Gainsborough & Worksop Times
Retford Trader & Guardian
Rhyl Prestatyn Abergele Journal
Richmond & Twickenham Informer
Richmond Twickenham Times Series
Ringwood Verwood & Fordingbridge Advertiser
Ripley & Heanor News
Rochdale Express
Rochdale Observer (Sat)
Rochdale Observer (Wed)
Romford & Havering Post
Romford Recorder Series
Romford Yellow Advertiser
Romsey Advertiser
Ross Gazette
Rossendale Free Press
Ross-shire Journal
Rotherham & South Yorkshire Advertiser
Rotherham Record
Royston Crow
Royston Weekly News
Rugby Advertiser
Rugby Observer
Rugby Review
Runcorn & Widnes Herald and Post
Runcorn & Widnes Weekly News
Runcorn & Widnes World Group
Rutherglen Reformer
Rutland Times
Rye & Battle Observer
Saffron Reporter & Dunmow Broadcast Group
Saffron Walden Weekly News
Sale & Altrincham Messenger
Salford Advertiser
Salisbury Avon Advertiser
Salisbury Journal
Sandwell & Great Barr Chronicle
Scarborough Evening News
Scarborough Trader
Scotland On Sunday
Scunthorpe Target
Scunthorpe Telegraph
SE Hants Property Guide
Seaham & Houghton Star Group
Selby & Goole Courier
Selby Times
Sevenoaks Chronicle
Sheerness Times Guardian
Sheffield Mercury
Sheffield Star
Sheffield Telegraph
Sheffield Weekly Gazette
Shetland Times
Shields Gazette
Shrewsbury Admag
Shrewsbury/North Shropshire Chronicle
Shropshire Star
Sidmouth Herald
Sittingbourne Extra
Sittingbourne/Sheppey Adscene - KRN
Skegness Standard
Skipton Adv & Craven Courier
Sleaford Standard
Slough & Windsor Express
Slough & Windsor Observer Midweek
Slough Eton & Windsor Observer
Solihull News
Solihull North Why
Solihull Shirley & Arden Observer Series
Somerset County Gazette
South Belfast News
South Bucks Wycombe & Chiltern Star
South Cheshire Advertiser Series
South Coast Leader
South Hams Gazette & News Group
South Lakes Citizen
South Lincs Target Group
South London Press (Fri)
South London Press (Tue)
South Manchester Reporter
South Shropshire/Mid Wales Journal
South Tyne Star
South Wales Argus - Newport
South Wales Echo
South Wales Evening Post
South Wales Guardian
South Yorkshire Times
Southampton - Southern Daily Echo
Southampton News Extra & Advertiser
Southend Standard
Southend Yellow Advertiser
Southern Reporter
Southport Midweek Visiter - Mediamix
Southport Visiter - Mediamix
Southwark News
Southwark Weekender
Spalding Guardian
St Albans & District Review
St Helens Reporter
St Helens Star
St Ives Times & Echo Group
Stafford Post
Staffordshire Newsletter
Staines Guardian Group
Staines Informer
Stamford Citizen
Stamford Mercury
Standard & Guardian Weekly Group Somerset
Stevenage Advertiser
Stirling News
Stirling Observer (Fri)
Stirling Observer (Wed)
Stirling/Alloa Hillsfoot Shopper
Stockport Express
Stockport Times Group
Stockton & Billingham Herald & Post
Stoke The Sentinel
Stornoway Gazette & West Coast Advertiser
Stourbridge Chronicle
Stourbridge News County Express
Strabane Chronicle
Strabane Weekly News
Stranraer & Wigtownshire Free Press
Stratford & Docklands Express
Stratford Observer
Stratford On Avon Midweek
Stratford Upon Avon Herald
Stratford Upon Avon Why
Stratford Yellow Advertiser
Strathaven Extra
Strathearn Herald
Strathspey & Badenoch Herald
Streatham Clapham & West Norwood Post
Streatham Guardian
Stretford & Urmston Messenger
Stroud Life
Stroud News & Journal
Suffolk & Ipswich Advertiser Group
Suffolk Free Press
Sunday Express
Sunday Herald - Scotland
Sunday Mail - Scotland
Sunday Mercury - Birmingham
Sunday Mirror
Sunday People
Sunday Post - Scotland
Sunday Sport
Sunday Telegraph
Sunday Times
Sunderland Echo
Sunderland Star
Surrey & Hants News Group
Surrey & Hants Star Courier
Surrey Advertiser
Surrey Comet Group
Surrey Herald News
Surrey Mirror Advertiser Series
Surrey Times Series
Sussex Express & County Herald
Sutton Coldfield News
Sutton Coldfield Observer
Sutton Guardian Series
Swanage & Wareham Advertiser
Swansea Herald Of Wales
Swindon Advertiser
Swindon Star
Tameside & Glossop Advertiser Group
Tamworth Herald Leader
Tamworth Herald Series
Taunton & Wellington Star
Tavistock Times Gazette
Teesdale Mercury
Teesside - Evening Gazette
Telford Journal
Tenby Observer Group
Thanet & District Adscene - KRN
Thanet Extra
Thanet Times - KRN
The Argus Brighton
The Buteman
The Chronicle - Northwich
The Citizen Gloucester
The Community Magazine
The Cornishman
The County Derry Post
The County Times & Express - Welshpool
The Courier Group - Garstang & Longridge
The Cumberland News
The Daily Jang
The Democrat (Tyrone)
The Echo
The Forester - Cinderford
The Gazette - Blackpool
The Glaswegian
The Guardian
The Guernsey Press & Star
The Herald - Scotland
The Herald (Plymouth)
The Hunts Post
The Independent
The Irish Times
The Islander
The Keswick Reminder
The Lakeland Echo
The Local - Bourne
The London Paper
The North Durham Advertiser Group
The Northern Echo
The Observer
The Orcadian - Orkney
The Post - Cardiff
The Press - York
The Press, Dewsbury
The Scotsman
The South Durham Advertiser Group
The Star Kennet & North Wiltshire
The Strathkelvin Advertiser
The Sun
The Target - Aire Wharfe & Worth
The Target - Bradford
The Times
The Universe
The Visitor - Morecambe
The Watford Free
The West Cumberland Times & Star
The Wharf
TheLondonPaper
Thetford & Watton Times Series
Thirsk Weekly News
Thorne Gazette
Thurrock Gazette
Thurrock Yellow Advertiser
Todmorden News & Advertiser
Torbay Weekender Group
Torquay Herald Express
Towcester Post
Trafford Metro News
Turriff Advertiser
Tyrone Constitution
Tyrone Herald
Tyrone Times
Uckfield & District Leader
Uckfield & District Property Leader
Ulster - News Letter
Ulster Gazette & Armagh Standard
Ulster Herald
Ulster Star
Uttoxeter Advertiser
Uttoxeter Post & Times
Uxbridge & Hillingdon Leader
Uxbridge Gazette Series
View From
Wakefield & Rothwell Extra
Wakefield Express
Walden Local
Wales - Western Mail
Wales on Sunday
Walsall Advertiser
Walsall Chronicle
Waltham Forest Guardian Series
Waltham Forest Independent
Waltham Forest Yellow Advertiser
Walton & Weybridge Informer
Wandsworth Guardian
Wanstead & Woodford Guardian
Warminster Journal
Warrington Guardian
Warrington Midweek
Warwick & Leamington Why
Warwickshire Times
Washington Star
Watford Observer
Waveney & District Advertiser
Wealden Advertiser
Wear Valley Mercury
Weekly Argus - Newport, Cwmbran & Risca
Wellingborough & Rushden Herald & Post
Welwyn & Hatfield Review
Welwyn & Hatfield Times
Wembley & Brent Times Series
West Briton
West Cumbrian Gazette
West End Extra
West Highland Free Press
West Lothian Courier
West Midlands Express & Star
West Somerset Free Press & News
West Somerset Trader
West Suffolk Mercury Group
West Sussex County Times
West Sussex Gazette
Western Daily Press
Western Gazette
Western Morning News
Western Telegraph
Westmorland Gazette
Weston & Somerset Mercury
Weymouth & Dorchester Advertiser
Wharfe Valley Times
Whitby Gazette - Friday
Whitby Gazette - Tuesday
Whitchurch Herald
Whitehaven News
Wigan Courier
Wigan Observer
Wigan Reporter
Wilmslow Express
Wilts & Gloucestershire Standard Series
Wiltshire Gazette & Herald
Wiltshire Star
Wiltshire Times
Wimbledon Guardian Series
Wimborne Advertiser
Winchester News Extra & Advertiser
Wirral Globe
Wirral News Group
Wishaw Press
Witney Gazette
Woking Informer
Woking News & Mail Series
Woking Review Series
Wokingham Times
Wolverhampton Chronicle
Worcester News
Worcester Standard
Worcester Why
Worksop Guardian Series
Worksop Trader
Worthing & District Advertiser
Worthing Guardian
Worthing Herald Group
Wrexham Chronicle
Wrexham Leader
Wythenshawe World
Y Cymro
Yeovil Express
Yeovil Times
York & Selby Star
Yorkshire Post
Your Leek Paper
EOT;


$candidates = explode( "\n", $newspapers );

$q = get_http_var('q');
$matches = array();
foreach( $candidates as $c ) {
    if( stripos( $c, $q ) !== FALSE ) {
        print "$c\n";
    }
}

?>
