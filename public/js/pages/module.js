import { api, query } from '../api.js';
import { t } from '../i18n.js';
import { $, html, mount, toast } from '../ui.js';
import { initPage, run } from '../shell.js';

const CONFIG = {
  payments: { permission:'payments.view', title:'module.payments.title', columns:['type','amount_baisa','method','reference','status','paid_at'], create:'payments.create', fields:['type','amount_baisa','method','reference','status','notes'] },
  invoices: { permission:'payments.view', title:'module.invoices.title', columns:['invoice_number','total_baisa','status','due_at','notes'], create:'payments.create', fields:['invoice_number','client_id','transaction_id','subtotal_baisa','official_fee_baisa','office_fee_baisa','total_baisa','status','due_at','notes'] },
  attendance: { permission:'attendance.view', title:'module.attendance.title', columns:['user_id','attendance_date','check_in','check_out','status','notes'], create:'attendance.manage', fields:['user_id','attendance_date','check_in','check_out','status','notes'] },
  clearance: { permission:'clearance.view', title:'module.clearance.title', columns:['transaction_id','authority','external_reference','status','submitted_at','completed_at','notes'], create:'clearance.manage', fields:['transaction_id','authority','external_reference','status','submitted_at','completed_at','notes'] },
  whatsapp: { permission:'whatsapp.view', title:'module.whatsapp.title', columns:['phone','direction','message_type','body','status','created_at'], create:'whatsapp.reply', fields:['phone','client_id','transaction_id','direction','message_type','body','status'] },
  discussions: { permission:'discussions.view', title:'module.discussions.title', columns:['transaction_id','user_id','channel','message','is_ai','created_at'], create:'discussions.manage', fields:['transaction_id','channel','message','is_ai'] },
  reports: { permission:'reports.view', title:'module.reports.title', columns:[], create:null, fields:[] },
};

const label = (field) => ({
  type:t('module.type'),amount_baisa:t('module.amount'),method:'Method',reference:t('module.reference'),status:t('module.status'),notes:t('module.notes'),
  invoice_number:t('module.invoice_number'),client_id:'Client ID',transaction_id:'Transaction ID',subtotal_baisa:'Subtotal',official_fee_baisa:'Official fee',office_fee_baisa:'Office fee',total_baisa:'Total',due_at:'Due date',
  user_id:t('module.user'),attendance_date:t('module.date'),check_in:'Check in',check_out:'Check out',authority:t('module.authority'),external_reference:'External reference',submitted_at:'Submitted',completed_at:'Completed',
  phone:t('module.phone'),direction:'Direction',message_type:'Message type',body:t('module.message'),channel:'Channel',message:t('module.message'),is_ai:'AI'
}[field] || field);

function renderValue(key, value) {
  if (key.endsWith('_baisa')) return `${(Number(value||0)/1000).toFixed(3)} ${t('common.omr')}`;
  if (value === null || value === undefined || value === '') return '—';
  return String(value);
}

function formFor(config) {
  return html`<form class="card" id="create-form"><div class="card-head"><h2>${t('module.new')}</h2></div><div class="card-body"><div class="form-grid">
    ${config.fields.map(f=>html`<div class="field"><label>${label(f)}</label><input class="input" name="${f}" ${f==='notes'||f==='body'||f==='message'?html`type="text"`:html`type="text"`} placeholder="${label(f)}"></div>`)}
  </div><div style="display:flex;justify-content:flex-end;margin-top:16px"><button class="btn btn-primary" type="submit">${t('common.save')}</button></div></div></form>`;
}

async function page(module) {
  const config=CONFIG[module];
  const { can, content, blocked } = await initPage({page:module,titleKey:config.title,permission:config.permission});
  if(blocked)return;

  if(module==='reports'){
    mount(content, html`<div class="toolbar"><div class="field"><label>${t('module.report_from')}</label><input class="input" id="from" type="date"></div><div class="field"><label>${t('module.report_to')}</label><input class="input" id="to" type="date"></div><button class="btn btn-primary" id="load">${t('module.refresh')}</button><button class="btn" id="ai">Generate AI report</button></div><section class="kpis" id="report-kpis"></section><section class="card"><div class="card-head"><h2>${t('module.reports.title')}</h2></div><div class="card-body"><p id="report-json" class="ltr"></p></div></section>`);
    const now=new Date(); $('#from').value=new Date(now.getFullYear(),now.getMonth(),1).toISOString().slice(0,10); $('#to').value=now.toISOString().slice(0,10);
    const load=async()=>{const r=await api.get(`reports/summary${query({from:$('#from').value,to:$('#to').value})}`);const d=r.data;mount($('#report-kpis'),html`<div class="card kpi"><span class="kpi-label">Transactions</span><span class="kpi-value num">${d.transactions}</span></div><div class="card kpi"><span class="kpi-label">Completed</span><span class="kpi-value num">${d.completed}</span></div><div class="card kpi"><span class="kpi-label">Revenue</span><span class="kpi-value num">${(d.revenue_baisa/1000).toFixed(3)} ${t('common.omr')}</span></div><div class="card kpi"><span class="kpi-label">New clients</span><span class="kpi-value num">${d.new_clients}</span></div>`);$('#report-json').textContent=JSON.stringify(d,null,2)}; $('#load').addEventListener('click',load); $('#ai').addEventListener('click',async()=>{try{const r=await api.post(`reports/ai${query({from:$('#from').value,to:$('#to').value})}`,{});$('#report-json').textContent=r.data.output;toast('AI report generated','success')}catch(e){toast(e.message,'error')}}); await load(); return;
  }

  mount(content, html`${can(config.create)?formFor(config):''}<section class="card"><div class="card-head"><div class="input-group grow"><input class="input" id="search" placeholder="${t('module.search')}"></div><button class="btn" id="refresh">${t('module.refresh')}</button></div><div class="table-wrap"><table class="table"><thead><tr>${config.columns.map(c=>html`<th>${label(c)}</th>`).join('')}</tr></thead><tbody id="rows"></tbody></table></div></section>`);
  const load=async()=>{const r=await api.get(`${module}${query({q:$('#search').value,limit:50})}`);const rows=r.data||[];mount($('#rows'),rows.length?html`${rows.map(row=>html`<tr>${config.columns.map(c=>html`<td class="${c.endsWith('_baisa')?'num':''}">${renderValue(c,row[c])}</td>`).join('')}</tr>`)}`:html`<tr><td colspan="20"><div class="empty">${t('module.empty')}</div></td></tr>`)};
  $('#refresh').addEventListener('click',load);$('#search').addEventListener('keydown',e=>{if(e.key==='Enter')load()});
  const form=$('#create-form'); if(form){form.addEventListener('submit',async e=>{e.preventDefault();const body=Object.fromEntries(new FormData(form).entries());for(const k of Object.keys(body))if(body[k]==='')delete body[k];try{await api.post(module,body);toast(t('common.created'),'success');form.reset();load()}catch(err){toast(err.message,'error')}})}
  await load();
}

const moduleName=new URLSearchParams(location.search).get('module') || document.body.dataset.module;
run(()=>page(moduleName));
