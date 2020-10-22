
//cut sliders
var slide1 = document.getElementById("rangebefore");
var slide2 = document.getElementById("rangeafter");
var video = document.getElementById("studio-cut-video");

 getVals();

function getVals(){
  var slide2 = document.getElementById("rangebefore");
  var slide1 = document.getElementById("rangeafter");
     slide1 = parseFloat( slide1.value );
     slide2 = parseFloat( slide2.value );
  if( slide1 > slide2 ){ 
      var tmp = slide2; slide2 = slide1; slide1 = tmp;
    }
    var displayElement = document.getElementById("rangeValues");
    displayElement.innerHTML = " " + getTimeFromSeconds(slide2) + " - " + getTimeFromSeconds(slide1);
}

document.getElementById("btnrange1").onclick = function() 
{
  slide1.value = video.currentTime;
  getVals();
};
document.getElementById("btnrange2").onclick = function() 
{
  slide2.value = video.currentTime;
  getVals();
};

window.onload = function(){
  // Initialize Sliders
  var sliderSections = document.getElementsByClassName("range-slider");
      for( var x = 0; x < sliderSections.length; x++ ){
        var sliders = sliderSections[x].getElementsByTagName("input");
        for( var y = 0; y < sliders.length; y++ ){
          if( sliders[y].type ==="range" ){
            sliders[y].oninput = getVals;
            // Manually trigger event first time to display values
            sliders[y].oninput();
          }
        }
      }
}

function getTimeFromSeconds(seconds) {
  var date = new Date(null);
  date.setSeconds(seconds);
  var formattedTime = date.toISOString().substr(11, 8);
  return formattedTime;
}
