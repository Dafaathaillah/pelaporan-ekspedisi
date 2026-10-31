import test from 'node:test';
import assert from 'node:assert/strict';
import { arrivalErrorMessage, submitArrivalUnits } from '../../resources/js/lib/arrivalSubmission.js';

test('arrival failures expose GPS validation, upload/server/session and network explanations', () => {
    assert.match(arrivalErrorMessage({response:{status:422,data:{errors:{arrival_latitude:['Lokasi wajib diisi'],planning_item_id:['Planning tidak valid']}}}}), /Lokasi wajib diisi.*Planning tidak valid/);
    assert.match(arrivalErrorMessage({response:{status:413}}), /ukuran upload server/);
    assert.match(arrivalErrorMessage({response:{status:419}}), /Sesi/);
    assert.match(arrivalErrorMessage({response:{status:500,data:{message:'SECRET'}}}), /server/);
    assert.doesNotMatch(arrivalErrorMessage({response:{status:500,data:{message:'SECRET'}}}), /SECRET/);
    assert.match(arrivalErrorMessage({request:{}}), /koneksi/);
});

test('only confirmed submissions count; missing cards and false results are failures', async () => {
    const result = await submitArrivalUnits(['a','b','c','d'], {a:{send:async()=>true},b:{send:async()=>{throw new Error('Lokasi belum siap')}},d:{send:async()=>false}});
    assert.deepEqual(result.map(x=>x.status), ['fulfilled','rejected','rejected','rejected']);
    assert.match(result[1].reason.message, /Lokasi/);
});
