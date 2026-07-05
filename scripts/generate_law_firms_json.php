<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Services/GoogleBusinessTextParserService.php';

use App\Services\GoogleBusinessTextParserService;

$raw = <<<'TEXT'
Sponsored
Mathebula Carpede Attorneys, Notaries & Conveyancers
5,0(5) · Legal services
8 Hillside Road
Closed · Opens 8 am Mon · 076 377 9147
Mathebula Carpede Attorneys - Professional Legal Solutions
Website
Directions
Sponsored
Leoni Naude Inc
4,9(623) · Law firm
65 8th Avenue
Closed · Opens 7:30 am Mon · 010 140 5775
24/7 Bail Help - Secure your freedom. Expert defense. Immediate help available
Website
Directions
Sponsored
Charl Groenewald - Franchise Attorney
Lawyer
1062 Jan Shoba Street
Closed · Opens 8 am Mon · 012 425 3586
Charl Groenewald - An expert in franchise law and director and partner at MacRobert Attorneys.
Website
Directions
Herbert Smith Freehills Kramer South Africa LLP
4,8(18) · Legal services
10+ years in business · Johannesburg
010 500 2600
Website
Directions
DLA Piper South Africa
4,8(19) · Legal services
10+ years in business · Sandton
Closed · Opens 8 am Mon · 011 302 0800
Website
Directions
Van Hulsteyns Attorneys
3,9(24) · Law firm
10+ years in business · Sandton
011 523 5300
Website
Directions
Mokgola Inc - Attorneys
5,0(12) · Law firm
Sandton
Closed · Opens 9 am Mon · 060 318 0952
"Brilliant attorneys, great service."
Website
Directions
MF Blandile Attorneys
5,0(3) · Law firm
Sandton
Closed · Opens 8 am Mon · 061 379 6964
Website
Directions
SST Attorneys (Sandton, Pretoria and Vaal Triangle)
4,3(46) · Legal services
10+ years in business · Sandton
Closed · Opens 8 am Mon · 010 443 2580
"Very helpful and understanding"
Website
Directions
Radasi Sekgatja Incorporated Attorneys
4,7(7) · Law firm
10+ years in business · Johannesburg
Closed · Opens 9 am Mon · 082 877 4327
"They showed compassion and communicated every detail to the core."
Website
Directions
Waldeck Attorneys & Notaries
4,8(20) · Law firm
10+ years in business · Johannesburg
Closed · Opens 8 am Mon · 011 431 4371
"What sets Waldeck's Attorneys apart is their personalized approach."
Website
Directions
Spoor & Fisher
4,5(120) · Law firm
10+ years in business · Pretoria
Closed · Opens 8 am Mon · 012 676 1111
"I highly recommend this law firm"
Website
Directions
Miranda & Associates
4,3(12) · Law firm
7+ years in business · Johannesburg
011 463 1142
"Very impressed!"
Website
Directions
Adams & Adams
4,3(28) · Law firm
115+ years in business · Sandton
Closed · Opens 8 am Mon · 011 895 1000
Website
Directions
Maluleke DN Attorneys Inc
5,0(13) · Law firm
3+ years in business · Johannesburg
Closed · Opens 8 am Mon · 064 967 6260
"Their expertise and prompt responses made everything smooth and stress-free."
Website
Directions
SD Law Johannesburg Attorney
4,4(8) · Law firm
7+ years in business
Open 24 hours · 076 116 0623
"Highly recommended this firm."
Website
Directions
CMS
4,4(8) · Law firm
7+ years in business · Sandton
Closed · Opens 8 am Mon · 087 210 0711
Website
Directions
O'Hagan Attorneys
5,0(235) · Law firm
15+ years in business · Johannesburg
Closed · Opens 8 am Mon · 011 029 6050
"Genuine Professionals I used O'Hagan Attorneys for conveyancing services."
Website
Directions
Allan Levin & Associates Attorneys
4,4(21) · Law firm
10+ years in business · Johannesburg
Closed · Opens 8:30 am Mon · 011 447 6171
Website
Directions
Fullard Mayer Morrison Inc
4,8(18) · Civil law attorney
Sandton
Closed · Opens 8 am Mon · 011 234 3029
"I would recommend Fullard Mayer Morrison to anyone requiring their services."
Website
Directions
Zulu Attorneys
4,9(7) · Law firm
Sandton
Closed · Opens 8 am Mon · 073 460 4707
Website
Directions
Moja Sibiya Attorneys
5,0(6) · Law firm
7+ years in business · Midrand
082 877 1850
"Top quality specialist attorneys"
Website
Directions
Adams & Adams
4,3(271) · Law firm
115+ years in business · Pretoria
Closed · Opens 8 am Mon · 012 432 6000
Website
Direction
TEXT;

$parser = new GoogleBusinessTextParserService();
$records = $parser->parseGoogleBusinessText($raw);

$outFile = __DIR__ . '/../storage/app/law_firms_google_business_list.json';
file_put_contents($outFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'Wrote ' . count($records) . ' records to ' . $outFile . PHP_EOL;
