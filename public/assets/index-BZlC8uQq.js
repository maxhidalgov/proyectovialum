import{r as p,E as _,f as w,e as a,ag as z,o as $,d as l,b as i,Z as y,t as r,a3 as k,v as n}from"./index-EEnC3NJa.js";import{V as C}from"./VContainer-CAAcXqCU.js";import{V as S}from"./VSpacer-jzULhY2u.js";import{V as B}from"./VTextField-aQZ6KkLC.js";import{V as N}from"./VCard-DafF2lSE.js";import{V as T}from"./VDataTable-D9yIGAOu.js";import{V as g}from"./VChip-CylSmJeS.js";import{V as A}from"./VSnackbar-4OqBb7vK.js";/* empty css              */import"./VAvatar-DxYUFzHW.js";import"./VImg-BYO3muGm.js";import"./transition-F63nSOWv.js";import"./VInput-DZt29aDg.js";import"./forwardRefs-D3j0TLhE.js";import"./VCardText-rhQY5LGw.js";import"./VSelect-BCMdEUpF.js";import"./VList-BhUfE36E.js";import"./VDivider-W_XghwXh.js";import"./dialog-transition-CCs7WQoe.js";import"./VMenu-BXdU604F.js";import"./VOverlay-DTtwWfhc.js";import"./VSelectionControl-B4PB9pA9.js";import"./VTable-4Avy399N.js";import"./filter-k4T3WdZJ.js";const D={class:"d-flex align-center gap-3 mb-4"},F={class:"font-weight-bold font-monospace"},P={class:"d-flex gap-1 justify-end"},j={class:"text-center py-8 text-medium-emphasis"},st={__name:"index",setup(E){const c=p([]),u=p(!1),f=p(""),d=p({show:!1,color:"success",msg:""}),b=[{title:"N°",key:"numero"},{title:"Fecha",key:"fecha"},{title:"Documento",key:"tipo"},{title:"Cliente",key:"cliente"},{title:"Piezas",key:"total_piezas"},{title:"",key:"acciones",sortable:!1,align:"end"}];async function h(){u.value=!0;try{const{data:e}=await z.get("/api/ordenes-corte",{params:{buscar:f.value||void 0}});c.value=Array.isArray(e)?e:[]}catch{c.value=[]}finally{u.value=!1}}function V(e){const o=(e.piezas||[]).map((m,v)=>`
    <tr>
      <td style="text-align:center">${v+1}</td>
      <td>${m.producto}</td>
      <td style="text-align:center">${m.ancho??"—"}</td>
      <td style="text-align:center">${m.alto??"—"}</td>
      <td style="text-align:center">${m.piezas}</td>
      <td style="text-align:center">${m.pulido?"Sí":"—"}</td>
    </tr>`).join(""),t=`
    <html><head><title>Orden de Corte ${e.numero}</title>
    <style>
      body{font-family:Arial,Helvetica,sans-serif;color:#222;padding:24px}
      h1{color:#6a1b9a;margin:0 0 2px;font-size:22px}
      .sub{color:#666;font-size:13px;margin-bottom:16px}
      table{width:100%;border-collapse:collapse;margin-top:8px}
      th,td{border:1px solid #ccc;padding:8px 10px;font-size:14px}
      th{background:#f3e5f5;text-align:left}
      .tot{margin-top:14px;font-size:12px;color:#666}
    </style></head><body>
      <h1>Vialum — Orden de Corte</h1>
      <div class="sub">${e.numero} · ${e.tipo} ${e.doc_numero?"N° "+e.doc_numero:""} · ${e.cliente||"Consumidor Final"} · ${x(e.fecha)}</div>
      <table>
        <thead><tr><th style="width:36px">#</th><th>Vidrio</th><th style="width:90px">Ancho (mm)</th><th style="width:90px">Alto (mm)</th><th style="width:70px">Piezas</th><th style="width:70px">Pulido</th></tr></thead>
        <tbody>${o}</tbody>
      </table>
      <p class="tot">Total de piezas a cortar: ${e.total_piezas}</p>
    </body></html>`,s=window.open("","_blank");if(!s){d.value={show:!0,color:"warning",msg:"Permite las ventanas emergentes para imprimir"};return}s.document.write(t),s.document.close(),s.focus(),setTimeout(()=>s.print(),300)}function x(e){return e?new Date(String(e).slice(0,10)+"T12:00:00").toLocaleDateString("es-CL",{day:"2-digit",month:"2-digit",year:"numeric"}):"—"}return _(h),(e,o)=>($(),w(C,{fluid:"",class:"pa-4"},{default:a(()=>[l("div",D,[i(y,{icon:"mdi-content-cut",size:"30",color:"info"}),o[2]||(o[2]=l("div",null,[l("h1",{class:"text-h5 font-weight-bold"},"Órdenes de Corte"),l("p",{class:"text-caption text-grey mt-1"},"Ventas con vidrios · reimprimibles para el taller")],-1)),i(S),i(B,{modelValue:f.value,"onUpdate:modelValue":[o[0]||(o[0]=t=>f.value=t),h],"prepend-inner-icon":"mdi-magnify",label:"Buscar cliente o N° doc",variant:"outlined",density:"compact","hide-details":"",clearable:"",style:{"max-width":"280px"}},null,8,["modelValue"])]),i(N,{variant:"outlined"},{default:a(()=>[i(T,{headers:b,items:c.value,loading:u.value,density:"compact","items-per-page":"25"},{"item.numero":a(({item:t})=>[l("span",F,n(t.numero),1)]),"item.fecha":a(({item:t})=>[r(n(x(t.fecha)),1)]),"item.tipo":a(({item:t})=>[i(g,{size:"x-small",color:t.tipo==="Boleta"?"secondary":"info",variant:"tonal"},{default:a(()=>[r(n(t.tipo)+" "+n(t.doc_numero?"#"+t.doc_numero:""),1)]),_:2},1032,["color"])]),"item.cliente":a(({item:t})=>[r(n(t.cliente||"Consumidor Final"),1)]),"item.total_piezas":a(({item:t})=>[i(g,{size:"x-small",color:"info",variant:"tonal"},{default:a(()=>[r(n(t.total_piezas)+" pieza"+n(t.total_piezas===1?"":"s"),1)]),_:2},1024)]),"item.acciones":a(({item:t})=>[l("div",P,[i(k,{size:"x-small",color:"info",variant:"tonal","prepend-icon":"mdi-printer",onClick:s=>V(t)},{default:a(()=>o[3]||(o[3]=[r("Reimprimir")])),_:2},1032,["onClick"])])]),"no-data":a(()=>[l("div",j,[i(y,{size:"40",class:"mb-2"},{default:a(()=>o[4]||(o[4]=[r("mdi-content-cut")])),_:1}),o[5]||(o[5]=l("p",null,"Sin órdenes de corte. Se generan al emitir una Venta Express con vidrios.",-1))])]),_:1},8,["items","loading"])]),_:1}),i(A,{modelValue:d.value.show,"onUpdate:modelValue":o[1]||(o[1]=t=>d.value.show=t),color:d.value.color,timeout:"3000",location:"top"},{default:a(()=>[r(n(d.value.msg),1)]),_:1},8,["modelValue","color"])]),_:1}))}};export{st as default};
