<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet exploer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '-')
{
 
  $str=trim($str);
 
  $_str = '';
  $i_max = strlen($str);
  for ($i=0; $i<strlen($str); $i++)
  {
   $ch = $str[$i];
   switch ($ch)
   {
     case '√Ñ': case '√Ü':
     $_str .= 'AE'; break;   
    
     case 'ä': case '√¶':
     $_str .= 'ae'; break;
    
     case '√†': case '√°':  case '√¢': case '√£':  case '√•':
     $_str .= 'a'; break;   
     case '√Ä': case '√Å':  case '√Ç': case '√É':  case '√Ö':
     $_str .= 'a'; break;   
    
     case '√á': case '√ß':
     $_str .= 'c'; break;
 
     case '√®': case '√©':  case '√™': case '√´':
     $_str .= 'e'; break;   
    
     case '√à': case '√â':  case '√ä': case '√ã':
     $_str .= 'E'; break;   
    
     case '√å': case '√ç':  case '√é': case '√è':
     $_str .= 'I'; break;   
     case '√¨': case '√≠':  case '√Æ': case '√Ø':
     $_str .= 'i'; break;   
    
     case '√ë': case '√±':
     $_str .= 'n'; break;
    
     case '√ñ': 
     $_str .= 'OE'; break;
    
     case '√∂':
     $_str .= 'oe'; break;
    
     case '√í': case '√ì':  case '√î': case '√ï':
     $_str .= 'O'; break;   
     case '√≤': case '√≥':  case '√¥': case '√µ':
     $_str .= 'i'; break;   
    
     case '√ü':
     $_str .= 'ss'; break;
    
     case '√ô': case '√ö':  case '√õ':
     $_str .= 'U'; break;   
     case '√π': case '√∫':  case '√ª':
     $_str .= 'u'; break;   
    
     case '√ú':
       $_str .= 'UE'; break;
      
     case '√º':
     $_str .= 'ue'; break;
    
     case '√ù':
       $_str .= 'Y'; break;
      
     case '√Ω': case '√ø':
     $_str .= 'y'; break;
    
     case '√ê':
     $_str .= 'D'; break;
    
     case ' ': $_str .= $spaceChar; break;

     case '/': case '\'': case '-': case ':':
     $_str .= '-'; break;
    
     default : if (ereg('[A-Za-z0-9\(\)]', $ch)) { $_str .= $ch;  } break;
   }
  }   
  
  $_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}", $_str);
  $_str = str_replace("{$spaceChar}-", '-', $_str);
  $_str = str_replace("-{$spaceChar}", '-', $_str);
 
  return $_str;
}
?>