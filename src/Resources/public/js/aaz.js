var Aaz = Aaz || {};
(function(nsp){

    nsp.utilis = {
        merge:function(target={},source={}){
            for(let i in source){
                target[i] = source[i];
            }
            return target;
        }
    };

    /**
     * Base Objet des events
     * @return {Event}
     */
    nsp.Event = (function(){
        function Event(type,params){
            this.type = type;
            this.params = {};
            this.isPropagationStoped = false;
            nsp.utilis.merge(this.params,params);
        };

        /**
         * permet de stopper la propagation d'un événement
         *
         * @return {null}
         */
        Event.prototype.stopPropagation = function(){
            this.isPropagationStoped = true;
        };

        return Event;
    })();

    /**
     * EventDispatcherSubscriber est le soucripteur d'evenement
     * @return {EventDispatcherSubscriber}
     */
    nsp.EventDispatcherSubscriber = (function(){
        function EventDispatcherSubscriber(dispatcher){
            this.dispatcher = dispatcher;
        };

        /**
         * annulation de souscription au gestionnaire d'évenements
         *
         * @return {null}
         */
        EventDispatcherSubscriber.prototype.unsubscribe = function(){
            this.dispatcher.remove(this);
        };

        return EventDispatcherSubscriber;
    })();

    /**
     * EventDispatcher est le gestionnaire d'evenement
     * @return {EventDispatcher}
     */
    nsp.EventDispatcher = (function(){
        function EventDispatcher(){
            this.$_data = new Map();
        };

        /**
         * souscription au gestionnaire d'évenements
         *
         * @param  {Function} cbk callback à appeler à chaque nouvel évenement
         * @return {null}
         */
        EventDispatcher.prototype.subscribe = function(cbk){
            var subscriber = new nsp.EventDispatcherSubscriber(this);
            this.$_data.set(subscriber,cbk);
            return subscriber;
        };

        /**
         * supprime un souscripteur d'évenement
         *
         * @param  {EventDispatcherSubscriber} subscriber
         * @return {null}
         */
        EventDispatcher.prototype.remove = function(subscriber){
            this.$_data.delete(subscriber);
        };

        /**
         * emetteur d'évenements
         *
         * @param  {Event} event
         * @return {null}
         */
        EventDispatcher.prototype.emit = function(event){
            for(let [i,cbk] of this.$_data){
                cbk.call(null,event);
                if(event.isPropagationStoped) break;
            }
        };
        return EventDispatcher;
    })();

    /**
     * evenement de scrolling dynamique
     */
    nsp.ScrollerEvent = (function(){
        function ScrollerEvent(params){
            nsp.Event.call(this,'scroll',params);
        };
        Object.assign(ScrollerEvent.prototype, nsp.Event.prototype);
        return ScrollerEvent;
    })();

    /**
     * @return {Toast}
     */
    nsp.Toast = (function(){
        function Toast(params){
            this.params = {};
            nsp.utilis.merge(this.params,params);
        };

        Toast.prototype.insert = function(msg_type,title,message){
            let toast = $(`<div class="toast toast-${msg_type}" aria-live="polite">
	            <div class="toast-title">${title}</div>
	            <div class="toast-message">${message}</div>
	        </div>`);

            let container = $("#toast-container");
            container.append(toast);
            container.addClass("open");

            setTimeout(()=>{
                toast.remove();
                container.removeClass("open");
            },10000);
        }

        return Toast;
    })();

    /**
     * l'infinite scrolling
     */
    nsp.Scroller = (function(){

        function Scroller(){
            nsp.EventDispatcher.call(this);
        };

        Object.assign(Scroller.prototype,nsp.EventDispatcher.prototype);

        Scroller.prototype.forWindow = function(key){

            var doc = jQuery(document);
            var win = jQuery(window);
            var oldPos = win.scrollTop();

            win.on({
                scroll:e=>{
                    var tHeight = doc.height() - win.height();
                    var scrollTop = win.scrollTop();
                    var pos = tHeight-scrollTop;
                    var percent = (pos*100)/tHeight;
                    var dir = scrollTop > oldPos ? "ttb":"btt";
                    oldPos = scrollTop;

                    var ev = {
                        scrollTop:scrollTop,
                        pos:pos,
                        percent:percent,
                        dir:dir
                    };
                    this.emit(new nsp.ScrollerEvent(nsp.utilis.merge({state:"scrolling"},ev)));

                    if (tHeight == scrollTop) {
                        this.emit(new nsp.ScrollerEvent(nsp.utilis.merge({state:"end"},ev)));
                    }
                    else if (scrollTop == 0) {
                        this.emit(new nsp.ScrollerEvent(nsp.utilis.merge({state:"start"},ev)));
                    }

                }
            });
        }
        return Scroller;
    })();
})(Aaz);