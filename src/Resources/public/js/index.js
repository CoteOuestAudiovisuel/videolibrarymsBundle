var vl = new Aaz.VideoLibrary();
vl.controller();

window.addEventListener("load",function(){
    var vs = new Aaz.VideoSprites();
    vs.init(".video-sprites");

    vl.subscribe(event=>{
        if(event.type == "newfeed"){
            vs.init(".video-sprites:not(.video-sprites-item)");
        }
    })
})