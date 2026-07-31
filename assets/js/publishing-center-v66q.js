(() => {
  const modal=document.querySelector('[data-publishing-center]');
  const frame=modal?.querySelector('[data-publishing-frame]');
  const empty=modal?.querySelector('[data-publishing-empty]');
  const loading=modal?.querySelector('[data-publishing-loading]');
  const options=Array.from(modal?.querySelectorAll('[data-publishing-option]')||[]);
  let returnFocus=null;
  const select=(key,forcedUrl='')=>{
    if(!modal||!frame)return;
    const option=options.find(item=>item.dataset.publishingOption===key)||options[0];
    const url=forcedUrl||option?.dataset.publishingUrl||'';
    if(!url)return;
    options.forEach(item=>item.classList.toggle('is-active',item===option));
    if(empty)empty.hidden=true;if(loading)loading.hidden=false;frame.hidden=true;frame.src=url;
    try{localStorage.setItem('nmm.publishing.last',option?.dataset.publishingOption||key)}catch(e){}
  };
  const open=(key='',url='')=>{
    if(!modal)return;returnFocus=document.activeElement;modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('publishing-center-open');
    const remembered=(()=>{try{return localStorage.getItem('nmm.publishing.last')||''}catch(e){return''}})();
    if(key||url)select(key||remembered,url);else if(options.length){if(empty)empty.hidden=false;if(frame)frame.hidden=true;if(loading)loading.hidden=true;}
    modal.querySelector('[data-publishing-close]')?.focus();
  };
  const close=(reload=false)=>{if(!modal)return;modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('publishing-center-open');if(frame){frame.src='about:blank';frame.hidden=true;}if(empty)empty.hidden=false;if(loading)loading.hidden=true;options.forEach(item=>item.classList.remove('is-active'));if(reload)window.location.reload();else if(returnFocus instanceof HTMLElement)returnFocus.focus();};
  document.addEventListener('click',event=>{const trigger=event.target.closest('[data-publishing-open]');if(trigger){event.preventDefault();open(trigger.dataset.publishingOpen||'',trigger.dataset.publishingUrl||'');return;}const option=event.target.closest('[data-publishing-option]');if(option){select(option.dataset.publishingOption||'',option.dataset.publishingUrl||'');}});
  modal?.querySelectorAll('[data-publishing-close]').forEach(button=>button.addEventListener('click',()=>close()));
  frame?.addEventListener('load',()=>{if(loading)loading.hidden=true;frame.hidden=false;try{const url=new URL(frame.contentWindow.location.href);if(url.searchParams.get('done')==='1'||(url.origin===location.origin&&!url.searchParams.has('modal')&&url.href!=='about:blank'))close(true);}catch(e){}});
  window.addEventListener('message',event=>{if(event.origin===location.origin&&event.data?.type==='nmm-publishing-complete')close(true);});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal&&!modal.hidden)close();});
  const settings=document.querySelector('[data-feed-settings-dialog]');
  document.querySelector('[data-feed-settings-open]')?.addEventListener('click',()=>settings?.showModal());
  document.querySelectorAll('[data-feed-settings-close]').forEach(button=>button.addEventListener('click',()=>settings?.close()));
  if(new URLSearchParams(location.search).get('create')==='1')window.requestAnimationFrame(()=>document.querySelector('[data-crm-contact-open]')?.click());
})();
