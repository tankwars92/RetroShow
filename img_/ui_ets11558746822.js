function showSheet(content)
{
	var sheet = document.getElementById('sheet');
	var sheetContent = document.getElementById('sheetContent');
	sheetContent.innerHTML = content;
	sheet.style.visibility = 'visible';
	return false;
}

function toggleVisibility(whichForm, setVisible)
{
	var newstate="none"
	if(setVisible == true) 
		newstate = ""

	//alert("element " + whichForm + " toggled to " + setVisible);
	
	if (document.getElementById)
	{
		// this is the way the standards work
		var style2 = document.getElementById(whichForm).style;
		style2.display = newstate;
	}
	else if (document.all)
	{
		// this is the way old msie versions work
		var style2 = document.all[whichForm].style;
		style2.display = newstate;
	}
	else if (document.layers)
	{
		// this is the way nn4 works
		var style2 = document.layers[whichForm].style;
		style2.display = newstate;
	}
}
	

function setInnerHTML(div_id, value)
{
	var dstDiv = document.getElementById(div_id);
	dstDiv.innerHTML = value;
}

function openPopup(url, name, height, width)
{
	newwindow=window.open(url, name,'height='+height+',width='+width);
	if (window.focus) {newwindow.focus()}
	return false;
}




function openDiv (elName) {
	var theElemenet = document.getElementById(elName);
	if (theElemenet) {
		theElemenet.style.display = "block";
	}
}
function closeDiv (elName) {
	var theElemenet = document.getElementById(elName);
	if (theElemenet) {
		theElemenet.style.display = "none";
	}
}

function showInline (elName) {
	var theElemenet = document.getElementById(elName);
	if (theElemenet) {
		theElemenet.style.display = "inline";
	}
}
function hideInline (elName) {
	var theElemenet = document.getElementById(elName);
	if (theElemenet) {
		theElemenet.style.display = "none";
	}
}


function blurElement (elName) {
	var theElement = document.getElementById(elName);
	if (theElement) {
		theElement.blur();
	}
}

function selectLink (elName) {
	var theElement = document.getElementById(elName);
	if (theElement) {
		theElement.className = "selectedNavLink";
	}
}
function unSelectLink (elName) {
	var theElement = document.getElementById(elName);
	if (theElement) {
		theElement.className = "unSelectedNavLink";
	}
}


function toggleDisplay(divName){
	tempDiv = document.getElementById(divName);
	if (!tempDiv) {
		return;
	}  
	 if (tempDiv.style.display=="block"){
	 	tempDiv.style.display="none";
	 }
	 else {	
	 	if (tempDiv.style.display=="none"){
			tempDiv.style.display="block";
		}
	 }
}

function hideDiv(divName){
	tempDiv = document.getElementById(divName);
	if (!tempDiv) {
		return;
	}
	if (tempDiv.style.display=="block"){
	     tempDiv.style.display="none";
	}
}

function showDiv(divName){
	tempDiv = document.getElementById(divName);
	if (!tempDiv) {
	  return;
	}
	if (tempDiv.style.display=="none"){
		tempDiv.style.display="block";
	 }
}


//Drop Down Menu Functions


function showDropdown() {
	toggleVisibility('myAccountDropdown',1);
	document.arrowImg.src='/img/icon_menarrwdrpdwn_down2_14x14.gif';
}

function showDropdownShow() {
	if(tempDropdownShow==1) {
		showDropdown();
		return false;
	}
	return false;
}

function hideDropwdown() {
	toggleVisibility('myAccountDropdown',0);
	document.arrowImg.src='/img/icon_menarrwdrpdwn_regular_14x14.gif';
	tempDropdownShow = 0;
	return false;
}

var tempDropdownShow = 0;
function arrowClicked() {
	if(tempDropdownShow==0) {
		showDropdown();
		document.arrowImg.blur();
		tempDropdownShow = 1;
		return false;
	}
	else {
		hideDropwdown();
		tempDropdownShow = 0;
		document.arrowImg.blur();
		return false;
	}
}


function changeBGcolor(tempDiv, onOrOff) {
	if(onOrOff==1) { tempDiv.style.backgroundColor='#DDD'; tempDiv.style.cursor='pointer';tempDiv.style.cursor='hand';}
	else {tempDiv.style.backgroundColor='#FFF'}

}
