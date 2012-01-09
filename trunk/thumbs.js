function thumbResize(thumb) {
        t=new Image;
        t.src=jQuery(thumb).attr('src');
        twidth = t.width;
        theight = t.height;

        if (!twidth || !theight) return false; //could not get dimensions
        new_height = theight;
        new_width = twidth;
        var classes = new Array;
        var dimensions = new Array(0, 0);
        var classList = jQuery(thumb).attr('class').split(/\s+/);
        for (var i = 0; i < classList.length; i++) {
            if (classList[i] == 'tt_resize') return false; //do not touch
            if (classList[i].substr(0, 9) === 'tt_thumb-') dimensions = classList[i].substr(9).split('x');
            else classes.push(classList[i]);
        }
        min_width = parseInt(dimensions[0]);
        min_height = parseInt(dimensions[1]);
        if (!min_width || !min_height) return false; //could not get dimensions
        // strange 1px bug on MSIE
        if (jQuery.browser.msie) {
            min_width = min_width + 1;
            min_height = min_height + 1;
        }
        //calculate the good size
        if (min_height < min_width) { //landscape
            ratio = min_width / min_height;
            tratio = twidth / theight;
            if (tratio > ratio) {
                new_height = min_height;
                new_width = parseInt(new_height * tratio);
            } else {
                new_width = min_width;
                new_height = parseInt(new_width / tratio);
            }
        } else { //portrait
            ratio = min_height / min_width;
            tratio = theight / twidth;
            if (tratio > ratio) {
                new_width = min_width;
                new_height = parseInt(new_width * tratio);
            } else {
                new_height = min_height;
                new_width = parseInt(new_height / tratio);
            }
        }
        if (parseInt(ratio * 100) == parseInt(tratio * 100)) return false; //no resize needed

        // adjust position (vertical and horizontal centering for css cropping)
        if (new_width > min_width) moveleft = '-' + parseInt((new_width - min_width) / 2) + 'px';
        else moveleft = 0;
        if (new_height > min_height) movetop = '-' + parseInt((new_height - min_height) / 2) + 'px';
        else movetop = 0;

        // resize thumb
        var styles = jQuery(thumb).getStyleObject();
        jQuery(thumb).removeClass().css({
            'width': new_width + 'px',
            'height': new_height + 'px',
            'position': 'absolute',
            'left': moveleft,
            'top': movetop,
            'margin': 0,
            'padding': 0,
            'border': 0,
            'display': 'inline',
            'float': 'none',
	        'z-index':0,	
            'max-width': 'none',
            'max-height': 'none'
        });

        jQuery(thumb).wrap('<div class="' + classes.join(' ') + '">');
        jQuery(thumb).parent().css(styles);
        jQuery(thumb).parent().css({
            'width': 'auto',
            'height': 'auto',
            'display': 'block'
        });

        jQuery(thumb).wrap('<div>');
        jQuery(thumb).parent().css({
            'width': min_width + 'px',
            'height': min_height + 'px',
            'display': 'block',
            'overflow': 'hidden',
            'margin': 0,
            'padding': 0,
            'position': 'relative',
//	        'z-index':500,	
            'border': 0
        });
        return true;
//    });
}

jQuery.fn.getStyleObject = function () {
    var dom = this.get(0);
    var style;
    var returns = {};
    if (window.getComputedStyle) {
        var camelize = function (a, b) {
                return b.toUpperCase();
            };
        style = window.getComputedStyle(dom, null);
        for (var i = 0, l = style.length; i < l; i++) {
            var prop = style[i];
            var camel = prop.replace(/\-([a-z])/g, camelize);
            var val = style.getPropertyValue(prop);
            returns[camel] = val;
        };
        return returns;
    };
    if (style = dom.currentStyle) {
        for (var prop in style) {
            returns[prop] = style[prop];
        };
        return returns;
    };
    if (style = dom.style) {
        for (var prop in style) {
            if (typeof style[prop] != 'function') {
                returns[prop] = style[prop];
            };
        };
        return returns;
    };
    return returns;
}
jQuery.fn.whenLoaded = function(fn){
   return this.each(function(){
     // if already loaded call callback
     if (this.complete || this.readyState == 'complete'){
       fn.call(this);
     } else { // otherwise bind onload event
       jQuery(this).load(fn);
     }
   });
}
 
function thumbProcess() {
   jQuery('[class^="tt_thumb-"]')
   .whenLoaded(function(){
      thumbResize(this);
   });
}

//thumbProcess();//process ASAP
jQuery(document).ready(function () {thumbProcess();});//process the rest
