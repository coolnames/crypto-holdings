<!DOCTYPE html>
<?php
/*
crypto holdings calc'd with coinmarketcap's api
by
  _  _   _  | ._   _. ._ _   _   _ 
 (_ (_) (_) | | | (_| | | | (/_ _> 

symbols and how much per are hardcoded and queried on page load.
pricing and holding values are user editable after page load, to play with 'what if' scenarios on-the-fly.
this is only meant to be used as a casual tool to check on your holdings; accuracy not guaranteed.
*/

/*------make sure to enter your data for below vars------*/

/* enter your crypto symbols to query here */
/* enter your holdings for each as a string in adjacent slot. If none, set as "0" */
$cryptos = array(
    "XRP", "31337",
    "SHIB", "100000000",
    "KUMA", "20000000000",
    "ETH", "0.11788015",
    "XLM", "1234",
    "DOGE", "0"
);

/* coinmarketcap API key goes here */
/* https://coinmarketcap.com/api/ */
$headers = [
  'Accepts: application/json',
  'X-CMC_PRO_API_KEY: YOURAPIKEYHERE'
];

/* $limit controls number of top ranked latest symbols returned.
lower value = faster.
symbols outside of this list will cost a additional API credit per query.
pick a number that will encompass most of your symbols list.
example:
    default $cryptos array's lowest rank symbol is KUMA @ ~#3400. Next lowest rank is XLM @ ~#24.
    setting $limit = "4000" would query all symbols in array, only cost 1 API credit, but be grossly inefficient and slow.
    setting $limit = "30" queries most symbols in array for 1 API credit, and KUMA will be queried separate, for an additional 1 API credit, and much faster. 2 API credits total.
https://coinmarketcap.com/api/documentation/v1/#operation/getV1CryptocurrencyQuotesLatest
*/
$limit = "30";

/*------make sure to enter your data for above vars------*/

/*sort out $cryptos array into separate arrays*/
$symbols = array();
$holdings = array();
foreach($cryptos as $i => $v){
    if(fmod($i, 2) == 0){ //symbols
        array_push($symbols, $v);
    }elseif(fmod($i, 2) == 1){ //holdings
        array_push($holdings, $v);
    }
}

/*truncate fractional cents*/
function trunc($val)
{
    if(abs($val) > 0.001){
        return number_format($val, 2, ".", "");
    }else{ //for very low value coins ex. Shib, Kuma | adjust as needed
        return number_format($val, 8, ".", "");
    }
}

/*return green/red for css classes*/
function rg($val){if($val>0){return "green";}else{return "red";}}
?>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>CRYPTO HOLDINGS</title>
    <meta name="description" content="Crypto holdings - Quotes from CMC applied to quatity held, with values totaled.">
    <style>
        /*color classes for positive or negative changes with symbol data*/
        @-webkit-keyframes colorPulseGreen {0% {color:green;} 25% {color:chartreuse;} 100% {color:chartreuse;}}
        @-webkit-keyframes colorPulseRed {0% {color: darkred;} 100% {color: red;}}
        @keyframes shimmer {0% {background-position: top left;} 40% {background-position: top right;} 100% {background-position: top right;}}
        .green{-webkit-animation: colorPulseGreen 1s infinite alternate;}
        .red{-webkit-animation: colorPulseRed 1s infinite alternate;}
        
        body{background-color: rgb(34, 34, 34); color:white; font-size: 2vw; font-family: "Roboto", "Helvetica", "Comic Sans MS", "Comic Sans", cursive;}
        .totcon{width: 100%; margin: auto; text-align: center;}
        #total{overflow-wrap:anywhere; font-size: 15vw; font-family: fantasy; margin: 5vh auto;}
        .shimmer {color: rgba(255,255,255,0.1); background: -webkit-gradient(linear, left top, right top, from(#1ec32b), to(#1deb70), color-stop(0.5, #fff)); background-size: 125px 100%; -webkit-background-clip: text; animation-name: shimmer; animation-duration: 5s; animation-iteration-count: infinite; background-repeat: no-repeat; background-position: 0 0; background-color: #08d300;}
        .clip{background-image: url(https://i.imgur.com/aF4ceGR.gif); background-size: cover; color: rgb(0 255 20 / 70%); -moz-background-clip: text; -webkit-background-clip: text; width: fit-content;}
        .cryptos{display: flex; flex-wrap: wrap; justify-content: space-around; align-items: flex-start;}
        .dsign{display: inline-block;font-size: 3vw;}
        .coin{flex-grow: 1; text-align: center; margin: 0vw;}
        .coin > div{margin: 5px auto; width: fit-content;}
        .coin-name{font-size: 4vw; font-weight: bold; text-shadow: 0px 1px 0px #ddd, 0px 2px 0px #bbb, 0px 3px 0px #999, 0px 4px 0px #777, 0px 5px 6px #001135;}
        .coin-price{font-size: 3vw; display: inline-block;}
        .coin-holdings{font-size:inherit;}
        .coin-holdings:hover, .coin-price:hover{outline: 1px white dotted;}
        .coin-hvalue{font-weight: bold;font-size: 3vw;}

    </style>
    <script>
    <?php
    /*returns json data from CoinMarketCap*/
    function queryCMC($symbol, $limit, $headers){
        //symbol present, retrieve json for specific symbol
        if($symbol != ''){$url = "https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest?symbol=".$symbol;}
        //no symbol passed, so grab latest with limit
        else{$url = "https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?limit=".$limit;}

        $request = "{$url}"; // create the request URL
        $curl = curl_init(); // Get cURL resource
        // Set cURL options
        curl_setopt_array($curl, array(
          CURLOPT_URL => $request,            // set the request URL
          CURLOPT_HTTPHEADER => $headers,     // set the headers 
          CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
        ));
        $response = curl_exec($curl); // Send the request, save the response
        $json = json_decode($response);
        curl_close($curl); // Close request
        return $json;
    }//end function
        
    $top = queryCMC("", $limit, $headers); //json data of latest + limit
    $data_ids = array_column($top->data, 'symbol'); //array of symbols from json

    /* loop that calls output function for each symbol in array */
    foreach ($symbols as $symbol) {
        //returns symbol ID from json data
        $symbol_data_id = array_search($symbol, $data_ids);
        if ($symbol_data_id == false){
            //symbol not in $top, so get json for specific symbol
            outputData($symbol, queryCMC($symbol, "", $headers)->data->$symbol->quote->USD);
        }else{
            //found in $top listings
            outputData($symbol, $top->data[$symbol_data_id]->quote->USD);
        }
    }

    /* array slots
    0 price
    1 1h change %
    2 1h color
    3 24h change %
    4 24h color
    5 7d change %
    6 7d color
    */
    /* builds js arrays for each symbol, with pricing data and css strings */
    function outputData($symbol, $ar){
        echo 'const '.$symbol.' = ["';
        echo trunc($ar->price).'", "';
        echo trunc($ar->percent_change_1h).'", "';
        echo rg($ar->percent_change_1h).'", "';
        echo trunc($ar->percent_change_24h).'", "';
        echo rg($ar->percent_change_24h).'", "';
        echo trunc($ar->percent_change_7d).'", "';
        echo rg($ar->percent_change_7d).'"];'."\n";
    }

    /*create coin symbol js array*/
    echo 'const coins = [';
    foreach ($symbols as $coin) {
        echo '"'.$coin.'", ';
    }
    echo '""];'."\n";
    /*create holdings js array*/
    echo 'holds = [';
    foreach ($holdings as $h) {
        echo '"'.$h.'", ';
    }
    echo '""];';
    ?>

    </script>
</head>
    
<body>
    <div class="totcon"><div id="total" class="clip shimmer"></div></div>
    <div class="cryptos">
        
    <script>

    let sum = 0;
    coins.forEach(loopFunc);
    function loopFunc(item, index, arr){
        if(item != ""){coinFunc(item, index);}
    }
    
    <?php /* loop through symbols and output HTML with queried/passed data */ ?>
    function coinFunc(cname, i){
        let arr = ["",""];
        switch(cname) {
            <?php
            foreach ($symbols as $c){
                echo 'case "'.$c.'":';
                echo 'arr = '.$c.';';
                echo 'break;';
            }
            ?>
        }
        <?php /*pass appropiate symbol's name, array, and index in main symbol array*/ ?>
        writeHtml(cname, arr, i); 
    }

    <?php
    /* array slots
    0 price
    1 1h change %
    2 1h color
    3 24h change %
    4 24h color
    5 7d change %
    6 7d color
    */?>
    <?php /* HTML structure of output per symbol listing */ ?>
    function writeHtml(cname, arr, i){
        document.write('<div id=\"'+cname+'\" class=\"coin\">');
            document.write('<div id=\"'+cname+'-name\" class=\"coin-name\">'+cname+'</div>');
            document.write('<div class=\"dsign\">$</div>');
            document.write('<div id=\"'+cname+'-price\" class=\"coin-price\" contentEditable=\"true\" onfocusout=\"updateHoldings(coins, holds)\" onkeypress=\"clickPress(event, coins, holds)\">'+arr[0]+'</div>');
            document.write('<div id=\"'+cname+'-1h\" class=\"coin-1h '+arr[2]+'\">'+arr[1]+'%</div>');
            document.write('<div id=\"'+cname+'-24h\" class=\"coin-24h '+arr[4]+'\">'+arr[3]+'%</div>');
            document.write('<div id=\"'+cname+'-7d\" class=\"coin-7d '+arr[6]+'\">'+arr[5]+'%</div>');
            document.write('<div id=\"'+cname+'-holdings\" class=\"coin-holdings\" contentEditable=\"true\" onfocusout=\"updateHoldings(coins, holds)\" onkeypress=\"clickPress(event, coins, holds)\">'+holds[i]+'</div>');
            document.write('<div id=\"'+cname+'-hvalue\" class=\"coin-hvalue\">$'+Intl.NumberFormat().format(Math.floor(holds[i]*arr[0]))+'</div>');
        document.write('</div>');
    }
    
    <?php /* if enterkey is pressed */ ?>
    function clickPress(event, coins, holds){
        if (event.keyCode == 13) {
            updateHoldings(coins, holds);
        }
    }    
    
    <?php /* Update USD values with newly edited holding value */ ?>
    function updateHoldings(coins, holds){
        var cArray = document.getElementsByClassName("coin");
        for (let i = 0; i < cArray.length; i++) {
            <?php /*price | remove bad chars*/ ?>
            p = cArray[i].children[2].innerHTML.replace(/[^\d.-]/g, '');
            <?php /*set div to cleaned value*/ ?>
            cArray[i].children[2].innerHTML = p;
            <?php /*holding | remove bad chars*/ ?>
            h = parseFloat(cArray[i].children[6].innerHTML.replace(/[^\d.-]/g, ''));
            <?php /*set div to cleaned value*/ ?>
            cArray[i].children[6].innerHTML = h;
            <?php /*new holding value*/ ?>
            hv = Math.floor(Number(p)*h);
            <?php /*set new holding value*/ ?>
            cArray[i].children[7].innerHTML = '$'+Intl.NumberFormat().format(hv);
        }
        <?php /*update total*/ ?>
        updateTotal();
    }
        
    
    <?php /*updates total holdings value of total div*/ ?>
    function updateTotal(){
        sum = 0;
        var hvalue = document.getElementsByClassName("coin-hvalue");
        <?php /*convert to number and add all up*/ ?>
        for (let i = 0; i < hvalue.length; i++) {
            n = hvalue[i].innerHTML;
            n = n.replace(/[^\d.-]/g, '');
            sum += Number(n);
        }
        <?php /*set total div to sum of holdings values*/ ?>
        document.getElementById("total").innerHTML = '$'+Intl.NumberFormat().format(sum);
    }
        
    <?php /* call once per page load */ ?>
    updateTotal();
        
    </script>
    </div>
    

    
<?php echo "<div style='position:fixed;bottom:3px;right:3px;font-size:1vw;text-align:right;'><a style='text-decoration:none;color:coral;' href='https://coolnam.es'>coolnames</a><br/>made for your wealth 🐋</div>"; ?>
</body>
</html>