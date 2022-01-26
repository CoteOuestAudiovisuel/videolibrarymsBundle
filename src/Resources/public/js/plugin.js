var Aaz = Aaz || {};


/**
 * animation video, a partir a webttv
 * @type {VideoSprites}
 */
Aaz.VideoSprites = (function(nsp){

    function VideoSprites(){
        this.data = [];
    }

    VideoSprites.prototype.animate = function (item){
        if(item.timerid) clearTimeout(item.timerid);
        item.timerid = setTimeout(()=>{

            if(item.x > 2){
                item.x = 0;
                item.y++;
            }

            if(item.y > 2 || item.x > 2){
                item.x = 0;
                item.y = 0;
                item.thumbnail++;

                if(item.thumbnail === 5){
                    item.thumbnail = 1;
                }
                let spriteUrl = item.baseUrl + "/" + "Thumbnail_00000000"+item.thumbnail+".jpg";
                item.el.get()[0].style.backgroundImage = 'url("'+spriteUrl+'")';
            }
            console.log("x="+item.x+", y="+item.y)

            item.el.css("background-position", `-${item.x*312}px -${item.y*176}px`);
            item.x++;

            this.animate(item);
        },250);
    }

    VideoSprites.prototype.init = function(className){
        $(className).each((i,el)=>{
            $el = $(el);
            let defaultImg = $el.css("background-image");
            if(/url\("(.+?)"\)/.test(defaultImg)){
                defaultImg = RegExp.$1;

                let baseUrl = defaultImg.substring(0,defaultImg.lastIndexOf("/"));
                let spriteUrl = baseUrl + "/" + "Thumbnail_000000001.jpg";
                let item = {
                    el:$el,
                    x:0,
                    y:0,
                    thumbnail:1,
                    baseUrl:baseUrl,
                    defaultImg:defaultImg,
                    timerid:null,
                };

                $el.on("mouseenter",(e)=>{
                    el.classList.add("active");
                    el.style.backgroundImage = 'url("'+spriteUrl+'")';
                    this.animate(item);
                });

                $el.on("mouseleave",(e)=>{
                    el.classList.remove("active");
                    el.style.backgroundImage = 'url("'+defaultImg+'")';
                    el.style.backgroundPosition = "0 0";

                    if(item.timerid) clearTimeout(item.timerid);
                });
            }
        });
    }
    return VideoSprites;
})(Aaz);

/**
 *
 * @type {VideoLibrary}
 */
Aaz.VideoLibrary = (function(nsp){

    function VideoLibrary(){
        nsp.EventDispatcher.call(this);

        this._toUpload = [];
        this._in_upload = false;
        this._getJobsStatusTimerid = null;
        this.currentFile = null;
        this.xhr = null;
    };

    Object.assign(VideoLibrary.prototype,nsp.EventDispatcher.prototype);

    // requete pour chargement progressif
    VideoLibrary.prototype.loadMore = function(limit,offset){
        return new Promise((resolve,reject)=>{
            $.ajax({
                url:"",
                get:"post",
                data:{limit:"limit",offset:offset},
                headers:{accept:"text/html"},
                dataType:"text",
                success:function(data){
                    resolve(data);
                },
                error:function(a,b,c){
                    reject("Oops un probleme est survenu, veuillez réessayer ulterieurement")
                },
                complete:function(e){

                }
            })
        })
    }

    // lance le processus d'upload multiple
    VideoLibrary.prototype.upload = function(){
        let e = this._toUpload.shift();
        this.currentFile = e;
        var $this = this;

        if(!e){
            return;
        }

        $("#upload-list").find(".counter").text(this._toUpload.length +1);
        this._in_upload = true;

        let file = e["file"];
        let filename = file.name;
        let obj = e["obj"];
        let a = obj.find("a");
        let progress_bar = obj.find(".progress-bar");
        obj.removeClass("pending").addClass("uploading");


        const chunkSize = 2097152;
        let chunkCounter = 0;
        let video_id  = "";
        let start = 0;
        let resp = null;

        let numberofChunks = Math.ceil(file.size/chunkSize);
        let chunkEnd = start + chunkSize;

        createChunk(start);

        function createChunk(start){
            chunkCounter++;
            chunkEnd = Math.min(start + chunkSize , file.size );
            const chunk = file.slice(start, chunkEnd,file.type);

            const chunkForm = new FormData();
            if(video_id){
                chunkForm.append('video_id', video_id);
            }
            chunkForm.append('file', chunk, filename);
            uploadChunk(chunkForm, start, chunkEnd);
        }

        function uploadChunk(chunkForm, start, chunkEnd){
            //var csrftoken = $('meta[name=csrf-token]').attr('content');
            let xhr = new XMLHttpRequest();
            $this.xhr = xhr;
            xhr.responseType = "json";
            xhr.addEventListener("progress", updateProgress);
            xhr.open("POST", "upload");
            let blobEnd = chunkEnd-1;
            let contentRange = "bytes "+ start+"-"+ blobEnd+"/"+file.size;
            xhr.setRequestHeader("Content-Range",contentRange);
            xhr.setRequestHeader("Accept","application/json");
            //xhr.setRequestHeader("X-CSRFToken", csrftoken)

            function updateProgress (oEvent) {
                if (oEvent.lengthComputable) {
                    let percentComplete = Math.round(oEvent.loaded / oEvent.total * 100);
                    let totalPercentComplete = Math.round((chunkCounter -1)/numberofChunks*100 +percentComplete/numberofChunks);
                    progress_bar.css("width",totalPercentComplete+"%");

                    if(totalPercentComplete >= 100){
                        obj.remove();
                    }
                }
                else {
                    // Unable to compute progress information since the total size is unknown
                }
            }

            xhr.addEventListener('load', () =>{
                let resp = xhr.response;

                if(resp && ~[200,206].indexOf(xhr.status)){
                    if(resp.video_id){
                        video_id = resp.video_id;
                    }

                    start += chunkSize;

                    if(start<file.size){
                        createChunk(start);
                    }
                    else{

                        if($this._toUpload.length === 0){
                            $("#upload-list").removeClass("open");
                            $this._in_upload = false;
                            $this.emit(new nsp.Event("upload_ended",{}));
                        }

                        $this.emit(new nsp.Event("upload_success",{"payload":resp, "file":file}));
                        $this.upload();

                    }

                    if(resp.status === "fails"){

                        $this.emit(new nsp.Event("upload_error",{"payload":resp, "file":file}));
                        let msg = `<div class="alert alert-danger">${resp.log}</div>`;
                        obj.append(msg);
                    }
                }
                else{
                    $this.emit(new nsp.Event("upload_error",{"payload":resp, "file":file}));
                    let msg = `<div class="alert alert-danger">impossible d'envoyer ce fichier:${xhr.statusText}</div>`;
                    obj.append(msg);
                    $this.upload();
                }

            });

            xhr.addEventListener('error', () =>{
                uploadChunk(chunkForm, start, chunkEnd)
            });

            xhr.send(chunkForm);
        }
    }

    VideoLibrary.prototype.sourceAvailable = function (files){
        let uploadlist = $("#upload-list");
        let uploadlist_container = uploadlist.find(".list-group");

        for(let file of files){
            let ext = file.name.split('.');
            ext = ext.slice(-1);
            ext = ext[0];
            ext = ext.toLowerCase();

            if (["mp4"].indexOf(ext) === -1){
                continue;
            }

            let li = $(`
                        <li class="list-group-item p-2 pending">
                            <a href="" class="float-right btn-close text-info">
                                <i class="fa fa-times"></i>
                            </a>

                            <div class="upload-list-item-name">
                                ${file.name}
                            </div>

                            <div class="progress-bar-xs progress mt-2" style="height: 1rem">
                                <div class="progress-bar bg-info" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
                            </div>

                        </li>`);

            let itemfile = {"file":file,"obj":li};

            li.find(".btn-close").on({
                click:(e)=>{
                    e.preventDefault();

                    let idx = this._toUpload.indexOf(itemfile);
                    if(~idx){
                        this._toUpload.splice(idx,1);
                    }
                    else{
                        if(this.currentFile === itemfile ){
                            this.xhr.abort();
                            this.upload();
                        }
                    }

                    li.remove();
                    if(uploadlist_container.find(">li").length === 0){
                        uploadlist.removeClass("open");
                    }
                }
            });

            this._toUpload.push(itemfile);
            uploadlist_container.append(li);
        }
        uploadlist.addClass("open");
        if(this._in_upload === false){
            this.upload();
        }
    }

    // initialisation du controller
    VideoLibrary.prototype.controller = function(){

        let btn_upload = $(".btn-upload");
        let uploadlist = $("#upload-list");
        let modal_remove = $(".modal-remove");
        let modal_screenshot = $(".modal-screenshot");
        var scroller = new Aaz.Scroller();


        /**
         * ouverture et fermeture de modal screenshot
         */
        modal_screenshot.on("shown.bs.modal",(e)=>{
            modal_screenshot.find(".card-body > .loading").show();
        });

        modal_screenshot.on("show.bs.modal",(e)=>{
            modal_screenshot.find(".card-body .screenshot-container").html("");
        });

        /**
         * clic pour reduire la fenetre des envois encours
         */
        uploadlist.find(".times").on("click",e=>{
            e.preventDefault();
            uploadlist.toggleClass("closed");
        });

        /**
         * gestion de la demande de suppression d'une video
         */
        let btn = modal_remove.find(".card-footer button.yes");
        btn.on({
            click:e=>{
                //btn.attr("disabled","");
                let code = modal_remove.attr("data-id");
                let tr = $("tr[data-id="+code+"]");
                $.post(`${code}/delete`);
                modal_remove.modal("hide");
                tr.remove();
            }
        });


        /**
         * permet de gerer toute les actions affichant une boite de dialogue
         */
        $('body').on("click",".call-modal",(e)=>{
            e.preventDefault();

            let el = $(e.currentTarget);
            let tr = el.parents("tr.data-item");
            let id = tr.attr("data-id");

            let title = tr.find(".data-item-name").text().trim();
            let modal = $(el.attr("data-target"));

            modal.find(".custom-title").html(title);
            modal.attr("data-id",id);

            if(modal.hasClass("modal-screenshot")){
                $.get(`${id}/screenshots`,function (html){
                    modal.find(".card-body > .loading").hide();
                    modal.find(".card-body .screenshot-container").html(html);
                });
            }
        });

        /**
         * changer la vignette d'une video
         */
        $('body').on("click",".modal-screenshot .btn-select-thumbnail",(e)=>{
            let el = $(e.target);
            let parent = el.parents("[data-key]:first");

            let code = modal_screenshot.attr("data-id");
            let tr = $("tr[data-id="+code+"]");
            $.post(`${code}/update-screenshot`, {"key":parent.attr("data-key")}, (data)=>{
                if(data.status){
                    tr.find(".data-item-image").get()[0].style.backgroundImage = `url("${data.url}")`;
                }
            });
            modal_screenshot.modal("hide");
        });

        /**
         * ajouter une vignette aux screenshots
         */
        $('body').on("click",".modal-screenshot .add",(e)=>{

            e.preventDefault();
            let input = $('<input multiple="true" type="file" accept="images/*" />');
            input.on({
                change:(ee)=>{
                    let files = ee.target.files;
                    //this.sourceAvailable(files);
                }
            })
            input.trigger("click");
        });


        /**
         * clique pour lancer un upload
         */
        btn_upload.on({
            click:(e)=>{
                let input = $('<input multiple="true" type="file" accept="video/mp4" />');
                input.on({
                    change:(ee)=>{
                        let files = ee.target.files;
                        this.sourceAvailable(files);
                    }
                })
                input.trigger("click");
            }
        });

        /**
         *  configuration visuel du drag & drop des fichiers a uploader
         */
        let body = $("body");
        let dropper = $(".drop-placeholder");
        body.on("dragenter dragover",(e)=>{
            e.preventDefault();
            if(~e.originalEvent.dataTransfer.types.indexOf("Files")){
                body.addClass("dragstart");
            }
        });

        dropper.on("dragleave dragend",(e)=>{
            if(~e.originalEvent.dataTransfer.types.indexOf("Files")){
                if(e.currentTarget === dropper.get()[0]){
                    body.removeClass("dragstart");
                }
            }
        });

        dropper.on("drop",(e)=>{
            e.preventDefault();
            body.removeClass("dragstart");

            let files = e.originalEvent.dataTransfer.files;
            if(!files.length) return;
            this.sourceAvailable(files);
        });


        /**
         * on ecoute certains evenements lors de l'upload
         */
        this.subscribe(event=>{
            if(event.type === "upload_success"){
                let payload = event.params.payload;
                let container = $("#main-container .card-body > table > tbody");

                let elts = $(payload.html);
                container.prepend(elts);
                this.createCirclesProgression(elts.find(".circle-progress"));
                this.getJobsStatus();
            }
            else if(event.type === "upload_ended"){
                //this.getJobsStatus();
            }
        });

        /**
         * gestion du scroll infini pour l'affichages des medias
         */
        scroller.subscribe(event=>{
            if(event instanceof Aaz.ScrollerEvent && event.params.percent <= 20 && event.params.dir === "ttb"){
                if(!$(document.body).hasClass("infinite-scroll-active")){
                    $(document.body).addClass("infinite-scroll-active")

                    let container = $("#main-container .card-body > table > tbody");

                    var limit = 20;
                    var offset = container.find(".data-item").length;

                    $.ajax({
                        url:"",
                        method:"GET",
                        data:{limit:limit,offset:offset},
                        headers:{accept:"text/html"},
                        dataType:"text",
                        success:function(data){
                            container.append(data);
                        },
                        error:function(a,b,c){

                        },
                        complete:function(e){
                            $(document.body).removeClass("infinite-scroll-active");
                        }
                    });
                }
            }
        });
        scroller.forWindow();



        let circles = $(".circle-progress");
        if(circles.length){
            this.createCirclesProgression(circles)
            this.getJobsStatus();
        }

        return this;
    }


    VideoLibrary.prototype.createCirclesProgression = function (items){
        items.circleProgress({
            size: 100,
            fill: {
                gradient: ["red", "orange"]
            }
        }).on('circle-animation-progress', function(event, progress,stepValue) {
            $(this).find('strong').html(Math.round(stepValue*100) + '<i style="font-size: 1rem">%</i>');
        });
    }


    VideoLibrary.prototype.getJobsStatus = function (){
        if(this._getJobsStatusTimerid) clearTimeout(this._getJobsStatusTimerid);

        this._getJobsStatusTimerid = setTimeout(()=>{

            $.ajax({
                url:"getStatus",
                method:"GET",
                dataType: "json",
                success:(data)=>{

                    if(data.status){
                        if(data.jobs && data.jobs.length){
                            for (let job of data.jobs){
                                let tr = $("tr[data-jobid="+job.id+"]");

                                if (tr.length) {
                                    let circle = tr.find(".circle-progress");
                                    if (job.status.toLowerCase() === "progressing") {
                                        if (job.jobPercent) {
                                            let currentPhase = job.currentPhase;
                                            tr.find(".data-item-state").text(job.status);
                                            tr.find(".data-item-state").text(job.status);

                                            circle.circleProgress("value", job.jobPercent / 100);
                                        }
                                    } else if (job.status.toLowerCase() === "complete") {
                                        circle.circleProgress("value", 1.0);
                                        tr.replaceWith($(job.html));
                                        //window.location.reload();
                                    }
                                }
                            }

                            this.getJobsStatus();
                        }
                    }
                },
                error:(a,b,c)=>{

                }
            });
        },10000);

    }
    return VideoLibrary;
})(Aaz);
