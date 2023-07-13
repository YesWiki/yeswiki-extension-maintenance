/*
 * Module that define VueJs component to rename a fieldname
 * 
 * This file is part of the YesWiki Extension maintenance.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (window.Vue == undefined){
    await import('../../../javascripts/vendor/vue/vue.min.js')
}

const isVueJS3 = (typeof Vue.createApp == "function");

const appParams = {
    data() {
        return {
            availableNewNames: {},
            availableOldNames: {},
            doneEntries: [],
            displaymessage: '',
            entries: {},
            formsData: {},
            isRenaming: false,
            newname: '',
            oldname: '',
            othernewname: '',
            otheroldname: '',
            restrictedFieldnames: [],
            selectedFormId: '',
            ready: false,
            token: ''
        }
    },
    computed:{
        canRename(){
            return !this.isRenaming
                && this.selectedFormId in this.availableNewNames
                && this.selectedFormId in this.availableOldNames
                && this.newname.length > 0
                && (
                    this.newname !== 'other' || (
                        this.othernewname.length > 0 
                        && !this.restrictedFieldnames.includes(this.othernewname)
                    )
                )
                && this.oldname.length > 0
                && (this.oldname !== 'other' || (
                    this.otheroldname.length > 0
                    && !this.restrictedFieldnames.includes(this.otheroldname)
                ))
        }
    },
    methods:{
        async getFormData(formId){
            if (!(formId in this.formsData)){
                let formData = new FormData();
                formData.append('token',this.token);
                const wiki = window.wiki
                this.formsData[formId] = await fetch(
                    wiki.url(`?api/forms/${formId}/renamefieldinfo`),
                    {
                        method: 'POST',
                        body: new URLSearchParams(formData),
                        headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
                    }
                ).then((response)=>{
                        if (!response.ok){
                            throw new Error(`response notok : code ${response.status}`)
                        } else {
                            return response.json()
                        }
                    })
            }
            return this.formsData[formId]
        },
        async importNewNames(formId){
            const form = await this.getFormData(formId)
            let names = form.availableNewNames ?? null
            if (names !== null){
                if (!names.includes('other')){
                    names.push('other')
                }
                this.$set(this.availableNewNames,String(formId),names)
            }
        },
        async importOldNames(formId){
            const form = await this.getFormData(formId)
            let oldNames = form.availableOldNames ?? null
            if (oldNames !== null){
                if (!oldNames.includes('other')){
                    oldNames.push('other')
                }
                this.$set(this.availableOldNames,String(formId),oldNames)
            }
        },
        isAllowed(word){
            return !this.restrictedFieldnames.includes(word)
        },
        manageError(err){
            if (window.wiki.isDebugEnabled){
                console.error(err)
            }
        },
        async rename(formId,oldname,newname){
            let formData = new FormData();
            formData.append('oldname',oldname);
            formData.append('newname',newname);
            formData.append('token',this.token);
            return await fetch(
                    window.wiki.url(`?api/forms/${formId}/renamefield`),
                    {
                        method: 'POST',
                        body: new URLSearchParams(formData),
                        headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
                    }
                )
                .then((response)=>{
                    if (!response.ok){
                        throw new Error(`response notok : code ${response.status}`)
                    } else {
                        return response.json()
                    }
                })
                .then((json)=>{
                    if (typeof json === 'object' && 'done' in json && 'entries' in json){
                        return {done:json.done,entries:json.entries}
                    }
                    throw new Error('response badly formatted')
                })
        },
        resetForm(formId){
            if (formId in this.availableOldNames){
                this.$delete(this.availableOldNames,formId)
            }
            if (formId in this.formsData){
                this.$delete(this.formsData,formId)
            }
            this.selectedFormId = ''
        },
        startRename(){
            if (this.canRename){
                this.displaymessage = ''
                this.doneEntries = []
                this.isRenaming = true
                this.rename(
                    this.selectedFormId,
                    this.oldname === 'other' ? this.otheroldname : this.oldname,
                    this.newname === 'other' ? this.othernewname : this.newname
                    )
                .then(({done,entries})=>{
                    if (done){
                        this.doneEntries = Array.isArray(entries)
                            ? entries
                            : []
                        this.displaymessage = 'success'
                        this.resetForm(this.selectedFormId)
                    } else {
                        this.displaymessage = 'nothingtodo'
                    }
                })
                .catch((err)=>{
                    this.displaymessage = 'trouble'
                    this.manageError(err)
                })
                .finally(()=>{
                    this.isRenaming = false
                })
            }
        },
    },
    mounted(){
        const el = isVueJS3 ? this.$el.parentNode : this.$el
        $(el).on('dblclick',function(e) {
            return false;
        });
        this.token = el.dataset.token
        const extractedrestrictedFieldnames = JSON.parse(el.dataset.restrictedFieldnames)
        this.restrictedFieldnames = Array.isArray(extractedrestrictedFieldnames) ? extractedrestrictedFieldnames : []
        this.ready = true
    },
    watch:{
        selectedFormId(newval){
            this.newname = ''
            this.othernewname = ''
            this.oldname = ''
            this.otheroldname = ''
            if (newval.trim().length > 0){
                this.displaymessage = ''
                this.doneEntries = []
                if (!(newval in this.availableNewNames)){
                    this.importNewNames(newval)
                        .then(()=>{
                            return this.importOldNames(newval)
                        })
                        .catch(this.manageError)
                } else if (!(newval in this.availableOldNames)){
                    this.importOldNames(newval).catch(this.manageError)
                }
            }
        }
    }
}

const load = (rootElem) => {
    if (isVueJS3){
        const app = window.Vue.createApp(appParams)
        app.config.globalProperties.wiki = wiki
        app.config.globalProperties._t = _t
        app.mount(rootElem)
    } else {
        window.Vue.prototype.wiki = wiki
        window.Vue.prototype._t = _t
        new window.Vue({
            ...{el:rootElem},
            ...appParams
        })
    }
}

const run = (rootElem) => {
    if (
        typeof document.readyState === 'string'
        && ['complete','loaded','interactive'].includes(document.readyState)
        ) {
        load(rootElem)
    } else {
        document.addEventListener('DOMContentLoaded',()=>{
            load(rootElem)
        })
    }
}

run('.rename-field-app')

export {run}