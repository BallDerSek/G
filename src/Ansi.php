<?php

if (!defined('ANN')) {
    
    define("ANN", "\033["); 
    define("FG256", ANN."38;5;"); define("BG256", ANN."48;5;");
    
    define("RSET", ANN."0m"); define("BOLD", ANN."1m");
    define("DIMM", ANN."2m"); define("ITAL", ANN."3m");
    define("UNDR", ANN."4m"); define("BLNK", ANN."5m");
    define("RPID", ANN."6m"); define("RVRS", ANN."7m");
    define("HDDN", ANN."8m"); define("STRK", ANN."9m"); 
    
    #foreround colours origin 
    define("FGo", [
      "BLK" => ANN."30m", "RED" => ANN."31m",
      "GRN" => ANN."32m", "YLW" => ANN."33m",
      "BLU" => ANN."34m", "MAG" => ANN."35m",
      "CYN" => ANN."36m", "WHT" => ANN."37m",
    ]);
    
    #foreround colours bright 
    define("FGb", [
      "BLK" => ANN."90m", "RED" => ANN."91m",
      "GRN" => ANN."92m", "YLW" => ANN."93m",
      "BLU" => ANN."94m", "MAG" => ANN."95m",
      "CYN" => ANN."96m", "WHT" => ANN."97m",
    ]);
    
    #foreround colours dark 
    define("FGd", [
      "BLK" => DIMM.FGo["BLK"], "RED" => DIMM.FGo["RED"],
      "GRN" => DIMM.FGo["GRN"], "YLW" => DIMM.FGo["YLW"],
      "BLU" => DIMM.FGo["BLU"], "MAG" => DIMM.FGo["MAG"],
      "CYN" => DIMM.FGo["CYN"], "WHT" => DIMM.FGo["WHT"],
    ]);
    
    #background colours 
    define("BG", [
      "BLK" => ANN."40m", "RED" => ANN."41m",
      "GRN" => ANN."42m", "YLW" => ANN."43m",
      "BLU" => ANN."44m", "MAG" => ANN."45m",
      "CYN" => ANN."46m", "WHT" => ANN."47m",
    ]);

    define('RBW', [
        FG256.'39m',
        FG256.'45m',
        FG256.'46m',
        FG256.'51m',
        FG256.'81m',
        FG256.'87m',
        FG256.'111m',
        FG256.'118m',
        FG256.'154m',
        FG256.'190m',
        FG256.'214m',
        FG256.'220m',
        FG256.'226m',
        FG256.'201m',
        FG256.'207m',
        FG256.'213m',
        FG256.'219m',
    ]);

}
