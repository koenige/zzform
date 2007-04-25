<?php
// ---------------------------------------------------------
// function forceFilename($str, $spaceChar)
//
// convert $str to a UNIX/Windows-conform filename
// a char for $spaceChar will replace the default whitespace '_'
// note when using '.' internet exploer adds automatically "[1]"
// for e.g. "This[1].is.a.filename.ext" in the save as dialog.
// ---------------------------------------------------------

function forceFilename($str, $spaceChar = '.')
{
 
  $str=trim($str);
 
  $_str = '';
  $i_max = strlen($str);
  for ($i=0; $i<strlen($str); $i++)
  {
   $ch = $str[$i];
   switch ($ch)
   {
     case '�': case '�':
     $_str .= 'AE'; break;   
    
     case '�': case '�':
     $_str .= 'ae'; break;
    
     case '�': case '�':  case '�': case '�':  case '�':
     $_str .= 'a'; break;   
     case '�': case '�':  case '�': case '�':  case '�':
     $_str .= 'a'; break;   
    
     case '�': case '�':
     $_str .= 'c'; break;
 
     case '�': case '�':  case '�': case '�':
     $_str .= 'e'; break;   
    
     case '�': case '�':  case '�': case '�':
     $_str .= 'E'; break;   
    
     case '�': case '�':  case '�': case '�':
     $_str .= 'I'; break;   
     case '�': case '�':  case '�': case '�':
     $_str .= 'i'; break;   
    
     case '�': case '�':
     $_str .= 'n'; break;
    
     case '�': 
     $_str .= 'OE'; break;
    
     case '�':
     $_str .= 'oe'; break;
    
     case '�': case '�':  case '�': case '�':
     $_str .= 'O'; break;   
     case '�': case '�':  case '�': case '�':
     $_str .= 'i'; break;   
    
     case '�':
     $_str .= 'ss'; break;
    
     case '�': case '�':  case '�':
     $_str .= 'U'; break;   
     case '�': case '�':  case '�':
     $_str .= 'u'; break;   
    
     case '�':
       $_str .= 'UE'; break;
      
     case '�':
     $_str .= 'ue'; break;
    
     case '�':
       $_str .= 'Y'; break;
      
     case '�': case '�':
     $_str .= 'y'; break;
    
     case '�':
     $_str .= 'D'; break;
    
     case ' ': $_str .= $spaceChar; break;

     case '/': case '\'': case '-': case ':':
     $_str .= '-'; break;
    
     default : if (preg_match('/[A-Za-z0-9\(\)]/', $ch)) { $_str .= $ch;  } break;
   }
  }   
  
  $_str = str_replace("{$spaceChar}{$spaceChar}", "{$spaceChar}", $_str);
  $_str = str_replace("{$spaceChar}-", '-', $_str);
  $_str = str_replace("-{$spaceChar}", '-', $_str);
 
  return $_str;
}
?>