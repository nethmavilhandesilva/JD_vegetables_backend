-- Convert items.type (vegetable names) from Sinhala to English.
-- Generated 2026-11-15 from the items table dump: 168 items, 167 converted
-- (121 / G-FRUIT is already English and is left alone).
--
-- BEFORE RUNNING: deploy the backend + frontend change that makes the banana Kuliya
-- rule (1.50) also match English names containing "Banana". Otherwise bananas sold
-- after this change get the normal weight-based Kuliya.
--
-- Safe to re-run: each row is only changed while it still has its original Sinhala
-- name, so items renamed since the dump are left alone.
-- Old sales / sales_histories rows keep the Sinhala item_name they were saved with.

SET NAMES utf8mb4;

-- 1. Backup (see the undo statement at the bottom of this file)
CREATE TABLE IF NOT EXISTS `items_backup_20261115` AS SELECT * FROM `items`;

-- 2. Rename
UPDATE `items` SET `type` = 'Alu Banana (Ash Plantain)', `updated_at` = NOW() WHERE `id` = 134 AND `type` = 'අලු කෙසෙල්'; -- AAB: අලු කෙසෙල්
UPDATE `items` SET `type` = 'Mustard', `updated_at` = NOW() WHERE `id` = 159 AND `type` = 'අබ'; -- ABA: අබ
UPDATE `items` SET `type` = 'Mustard (Bags)', `updated_at` = NOW() WHERE `id` = 95 AND `type` = 'අබ මලු'; -- ABM: අබ මලු
UPDATE `items` SET `type` = 'Mango (Boxes)', `updated_at` = NOW() WHERE `id` = 125 AND `type` = 'අඹ පෙට්ටි'; -- ABP: අඹ පෙට්ටි
UPDATE `items` SET `type` = 'Ambarella', `updated_at` = NOW() WHERE `id` = 143 AND `type` = 'ඇඹරැල්ල'; -- ABR: ඇඹරැල්ල
UPDATE `items` SET `type` = 'Ambul Banana', `updated_at` = NOW() WHERE `id` = 83 AND `type` = 'ඇඹුල් කෙසෙල්'; -- ABU: ඇඹුල් කෙසෙල්
UPDATE `items` SET `type` = 'Avocado', `updated_at` = NOW() WHERE `id` = 21 AND `type` = 'අලි පේර'; -- ABV: අලි පේර
UPDATE `items` SET `type` = 'Potato', `updated_at` = NOW() WHERE `id` = 85 AND `type` = 'අල'; -- ALL: අල
UPDATE `items` SET `type` = 'Ambun Banana', `updated_at` = NOW() WHERE `id` = 46 AND `type` = 'ඇම්බන් කෙසෙල්'; -- AMB: ඇම්බන් කෙසෙල්
UPDATE `items` SET `type` = 'Pineapple', `updated_at` = NOW() WHERE `id` = 58 AND `type` = 'අන්නාසි'; -- ANN: අන්නාසි
UPDATE `items` SET `type` = 'Soursop (Anoda)', `updated_at` = NOW() WHERE `id` = 109 AND `type` = 'අනෝදා'; -- ANO: අනෝදා
UPDATE `items` SET `type` = 'Asamodagam (Bundles)', `updated_at` = NOW() WHERE `id` = 215 AND `type` = 'අසමෝදගම් මිටි'; -- ASA: අසමෝදගම් මිටි
UPDATE `items` SET `type` = 'Brussels Sprouts', `updated_at` = NOW() WHERE `id` = 80 AND `type` = 'ඇට ගෝවා'; -- ATA: ඇට ගෝවා
UPDATE `items` SET `type` = 'Avara Beans', `updated_at` = NOW() WHERE `id` = 147 AND `type` = 'අවර'; -- AVA: අවර
UPDATE `items` SET `type` = 'Avari Banana', `updated_at` = NOW() WHERE `id` = 105 AND `type` = 'ඇවරි කෙසෙල්'; -- AWR: ඇවරි කෙසෙල්
UPDATE `items` SET `type` = 'Alu Mundam', `updated_at` = NOW() WHERE `id` = 118 AND `type` = 'අලු මුන්දම්'; -- AXA: අලු මුන්දම්
UPDATE `items` SET `type` = 'Big Onion', `updated_at` = NOW() WHERE `id` = 207 AND `type` = 'බී ලූනූ'; -- BAA: බී ලූනූ
UPDATE `items` SET `type` = 'Beans', `updated_at` = NOW() WHERE `id` = 16 AND `type` = 'බෝංචි'; -- BAB: බෝංචි
UPDATE `items` SET `type` = 'Beetroot', `updated_at` = NOW() WHERE `id` = 216 AND `type` = 'බීට්'; -- BAC: බීට්
UPDATE `items` SET `type` = 'Butter Beans', `updated_at` = NOW() WHERE `id` = 217 AND `type` = 'බටර් බෝංචි'; -- BAD: බටර් බෝංචි
UPDATE `items` SET `type` = 'Supeen Beetroot', `updated_at` = NOW() WHERE `id` = 218 AND `type` = 'සූපීන් බීට්'; -- BAE: සූපීන් බීට්
UPDATE `items` SET `type` = 'Indian Onion', `updated_at` = NOW() WHERE `id` = 219 AND `type` = 'ඉන්දියන් ලුනූ'; -- BAI: ඉන්දියන් ලුනූ
UPDATE `items` SET `type` = 'Peas (Batana)', `updated_at` = NOW() WHERE `id` = 220 AND `type` = 'බටානා'; -- BBC: බටානා
UPDATE `items` SET `type` = 'Small Peas', `updated_at` = NOW() WHERE `id` = 221 AND `type` = 'පොඩි බටානා'; -- BBD: පොඩි බටානා
UPDATE `items` SET `type` = 'Red Sweet Potato', `updated_at` = NOW() WHERE `id` = 222 AND `type` = 'රතු බතල'; -- BDA: රතු බතල
UPDATE `items` SET `type` = 'Sweet Potato (Supeen)', `updated_at` = NOW() WHERE `id` = 223 AND `type` = 'බතල සූපින්'; -- BDB: බතල සූපින්
UPDATE `items` SET `type` = 'Large Sweet Potato', `updated_at` = NOW() WHERE `id` = 224 AND `type` = 'ලොකු බතල'; -- BDC: ලොකු බතල
UPDATE `items` SET `type` = 'Purple Sweet Potato', `updated_at` = NOW() WHERE `id` = 225 AND `type` = 'දම් බතල'; -- BDG: දම් බතල
UPDATE `items` SET `type` = 'Beli (Bael Fruit)', `updated_at` = NOW() WHERE `id` = 226 AND `type` = 'බෙලි'; -- BEL: බෙලි
UPDATE `items` SET `type` = 'Okra (Ladies'' Fingers)', `updated_at` = NOW() WHERE `id` = 227 AND `type` = 'බණ්ඩක්කා'; -- BNA: බණ්ඩක්කා
UPDATE `items` SET `type` = 'Bingo', `updated_at` = NOW() WHERE `id` = 228 AND `type` = 'බින්ගෝ'; -- BNN: බින්ගෝ
UPDATE `items` SET `type` = 'Small Bingo', `updated_at` = NOW() WHERE `id` = 229 AND `type` = 'පොඩි බින්ගෝ'; -- BOO: පොඩි බින්ගෝ
UPDATE `items` SET `type` = 'Betel Leaves', `updated_at` = NOW() WHERE `id` = 230 AND `type` = 'බුලත් කොල'; -- BRA: බුලත් කොල
UPDATE `items` SET `type` = 'Batu Karawila', `updated_at` = NOW() WHERE `id` = 231 AND `type` = 'බටු කරවිල'; -- BTK: බටු කරවිල
UPDATE `items` SET `type` = 'Bullnose Capsicum', `updated_at` = NOW() WHERE `id` = 232 AND `type` = 'බුල්නෝස්'; -- BUN: බුල්නෝස්
UPDATE `items` SET `type` = 'Cut Beet', `updated_at` = NOW() WHERE `id` = 233 AND `type` = 'කට් බීට්'; -- CAA: කට් බීට්
UPDATE `items` SET `type` = 'Chow Chow', `updated_at` = NOW() WHERE `id` = 234 AND `type` = 'චව්'; -- CHU: චව්
UPDATE `items` SET `type` = 'CIC Bingo', `updated_at` = NOW() WHERE `id` = 235 AND `type` = 'CIC බින්ගෝ'; -- CIC: CIC බින්ගෝ
UPDATE `items` SET `type` = 'Lime', `updated_at` = NOW() WHERE `id` = 236 AND `type` = 'දෙහි'; -- DAA: දෙහි
UPDATE `items` SET `type` = 'Breadfruit', `updated_at` = NOW() WHERE `id` = 237 AND `type` = 'දෙල්'; -- DAB: දෙල්
UPDATE `items` SET `type` = 'Lime - Ripe', `updated_at` = NOW() WHERE `id` = 238 AND `type` = 'දෙහි-ඉදුණු'; -- DAC: දෙහි-ඉදුණු
UPDATE `items` SET `type` = 'Pomegranate', `updated_at` = NOW() WHERE `id` = 239 AND `type` = 'දෙලුම්'; -- DEE: දෙලුම්
UPDATE `items` SET `type` = 'Winged Bean', `updated_at` = NOW() WHERE `id` = 240 AND `type` = 'දඹල'; -- DEL: දඹල
UPDATE `items` SET `type` = 'Orange', `updated_at` = NOW() WHERE `id` = 241 AND `type` = 'දොඩම්'; -- DHO: දොඩම්
UPDATE `items` SET `type` = 'Wood Apple', `updated_at` = NOW() WHERE `id` = 242 AND `type` = 'දිවුල්'; -- DIV: දිවුල්
UPDATE `items` SET `type` = 'Durian', `updated_at` = NOW() WHERE `id` = 243 AND `type` = 'ඩූරියන්'; -- DUU: ඩූරියන්
UPDATE `items` SET `type` = 'Elabatu', `updated_at` = NOW() WHERE `id` = 244 AND `type` = 'එලබටු'; -- EAA: එලබටු
UPDATE `items` SET `type` = 'Ripe Elabatu', `updated_at` = NOW() WHERE `id` = 245 AND `type` = 'ඉදුනූ එලබටු'; -- EAB: ඉදුනූ එලබටු
UPDATE `items` SET `type` = 'Cabbage', `updated_at` = NOW() WHERE `id` = 246 AND `type` = 'ගෝවා'; -- GAA: ගෝවා
UPDATE `items` SET `type` = 'Velvet Tamarind (Gal Siyambala)', `updated_at` = NOW() WHERE `id` = 247 AND `type` = 'ගල් සියඹලා'; -- GAL: ගල් සියඹලා
UPDATE `items` SET `type` = 'Papaya', `updated_at` = NOW() WHERE `id` = 248 AND `type` = 'ගස්ලබු'; -- GAS: ගස්ලබු
UPDATE `items` SET `type` = 'Gherkin', `updated_at` = NOW() WHERE `id` = 249 AND `type` = 'ජර්කින්'; -- GER: ජර්කින්
UPDATE `items` SET `type` = 'Cabbage Branches (Gova Athu)', `updated_at` = NOW() WHERE `id` = 250 AND `type` = 'ගෝවා අතු'; -- GOA: ගෝවා අතු
UPDATE `items` SET `type` = 'Cabbage Core (Gova Mada)', `updated_at` = NOW() WHERE `id` = 251 AND `type` = 'ගෝවා මද'; -- GOB: ගෝවා මද
UPDATE `items` SET `type` = 'Cabbage Leaves', `updated_at` = NOW() WHERE `id` = 252 AND `type` = 'ගෝවා කොල'; -- GOK: ගෝවා කොල
UPDATE `items` SET `type` = 'Gotukola', `updated_at` = NOW() WHERE `id` = 253 AND `type` = 'ගොටු කොල'; -- GOT: ගොටු කොල
UPDATE `items` SET `type` = 'Gaduguda', `updated_at` = NOW() WHERE `id` = 254 AND `type` = 'ගඩුගුඩා'; -- GTT: ගඩුගුඩා
UPDATE `items` SET `type` = 'Cauliflower', `updated_at` = NOW() WHERE `id` = 255 AND `type` = 'ගොවා මල්'; -- GWA: ගොවා මල්
UPDATE `items` SET `type` = 'Sweet Corn', `updated_at` = NOW() WHERE `id` = 256 AND `type` = 'බඩ ඉරිගු'; -- IAA: බඩ ඉරිගු
UPDATE `items` SET `type` = 'Innala (Country Potato)', `updated_at` = NOW() WHERE `id` = 257 AND `type` = 'ඉන්නල'; -- IAB: ඉන්නල
UPDATE `items` SET `type` = 'Ginger', `updated_at` = NOW() WHERE `id` = 258 AND `type` = 'ඉගුරු'; -- IRI: ඉගුරු
UPDATE `items` SET `type` = 'Jambu (Rose Apple)', `updated_at` = NOW() WHERE `id` = 259 AND `type` = 'ජම්බු'; -- JAM: ජම්බු
UPDATE `items` SET `type` = 'Jambola (Pomelo)', `updated_at` = NOW() WHERE `id` = 260 AND `type` = 'ජම්බෝල'; -- JMM: ජම්බෝල
UPDATE `items` SET `type` = 'Bitter Gourd', `updated_at` = NOW() WHERE `id` = 261 AND `type` = 'කරවිල'; -- KAA: කරවිල
UPDATE `items` SET `type` = 'Curry Leaves', `updated_at` = NOW() WHERE `id` = 262 AND `type` = 'කරපිංචා'; -- KAB: කරපිංචා
UPDATE `items` SET `type` = 'Kiri Ala (Taro)', `updated_at` = NOW() WHERE `id` = 263 AND `type` = 'කිරි අල'; -- KAC: කිරි අල
UPDATE `items` SET `type` = 'Kankun (Water Spinach)', `updated_at` = NOW() WHERE `id` = 264 AND `type` = 'කංකුං'; -- KAD: කංකුං
UPDATE `items` SET `type` = 'Yellow Sweet Potato', `updated_at` = NOW() WHERE `id` = 265 AND `type` = 'කහ බතල'; -- KAE: කහ බතල
UPDATE `items` SET `type` = 'Star Fruit (Kamaranga)', `updated_at` = NOW() WHERE `id` = 266 AND `type` = 'කාබරන්කා'; -- KAF: කාබරන්කා
UPDATE `items` SET `type` = 'Turmeric', `updated_at` = NOW() WHERE `id` = 267 AND `type` = 'කහ'; -- KAG: කහ
UPDATE `items` SET `type` = 'Katurumurunga', `updated_at` = NOW() WHERE `id` = 268 AND `type` = 'කතුරු මුරුංගා'; -- KAH: කතුරු මුරුංගා
UPDATE `items` SET `type` = 'Cashew', `updated_at` = NOW() WHERE `id` = 269 AND `type` = 'කජූ'; -- KAJ: කජූ
UPDATE `items` SET `type` = 'S-Carrot', `updated_at` = NOW() WHERE `id` = 270 AND `type` = 'S-කැරට්'; -- KAS: S-කැරට්
UPDATE `items` SET `type` = 'Cavendish', `updated_at` = NOW() WHERE `id` = 271 AND `type` = 'කැවැන්ඩිස්'; -- KAWA: කැවැන්ඩිස්
UPDATE `items` SET `type` = 'Kekiri (Snake Melon)', `updated_at` = NOW() WHERE `id` = 272 AND `type` = 'කැකිරි'; -- KDD: කැකිරි
UPDATE `items` SET `type` = 'Banana Blossom', `updated_at` = NOW() WHERE `id` = 273 AND `type` = 'කෙසෙල් මුව'; -- KES: කෙසෙල් මුව
UPDATE `items` SET `type` = 'Kidaran Ala (Elephant Foot Yam)', `updated_at` = NOW() WHERE `id` = 274 AND `type` = 'කිඩාරන් අල'; -- KID: කිඩාරන් අල
UPDATE `items` SET `type` = 'Carrot', `updated_at` = NOW() WHERE `id` = 275 AND `type` = 'කැරට්'; -- KK: කැරට්
UPDATE `items` SET `type` = 'Jackfruit Seeds', `updated_at` = NOW() WHERE `id` = 276 AND `type` = 'කොස් ඇට'; -- KKK: කොස් ඇට
UPDATE `items` SET `type` = 'Kochchi (Bird''s Eye Chilli)', `updated_at` = NOW() WHERE `id` = 278 AND `type` = 'කොච්චී'; -- KOH: කොච්චී
UPDATE `items` SET `type` = 'Kohila', `updated_at` = NOW() WHERE `id` = 277 AND `type` = 'කොහිල'; -- KOHI: කොහිල
UPDATE `items` SET `type` = 'Kolikuttu', `updated_at` = NOW() WHERE `id` = 279 AND `type` = 'කෝලි කුට්ටු'; -- KOI: කෝලි කුට්ටු
UPDATE `items` SET `type` = 'Leaf Beet', `updated_at` = NOW() WHERE `id` = 280 AND `type` = 'කොල බීට්'; -- KOL: කොල බීට්
UPDATE `items` SET `type` = 'Watermelon', `updated_at` = NOW() WHERE `id` = 281 AND `type` = 'කොමඩු'; -- KOM: කොමඩු
UPDATE `items` SET `type` = 'Small Watermelon', `updated_at` = NOW() WHERE `id` = 282 AND `type` = 'පොඩි කොමඩු'; -- KON: පොඩි කොමඩු
UPDATE `items` SET `type` = 'Jackfruit', `updated_at` = NOW() WHERE `id` = 283 AND `type` = 'කොස්'; -- KOO: කොස්
UPDATE `items` SET `type` = 'Kotta Pathola (Short Snake Gourd)', `updated_at` = NOW() WHERE `id` = 284 AND `type` = 'කොට්ට පතෝල'; -- KOT: කොට්ට පතෝල
UPDATE `items` SET `type` = 'Coriander', `updated_at` = NOW() WHERE `id` = 285 AND `type` = 'කොත්තමල්ලි'; -- KOU: කොත්තමල්ලි
UPDATE `items` SET `type` = 'Leeks', `updated_at` = NOW() WHERE `id` = 289 AND `type` = 'ලීක්ස්'; -- LAA: ලීක්ස්
UPDATE `items` SET `type` = 'Lavulu (Canistel)', `updated_at` = NOW() WHERE `id` = 287 AND `type` = 'ලාවුලු'; -- LAC: ලාවුලු
UPDATE `items` SET `type` = 'Lemon', `updated_at` = NOW() WHERE `id` = 286 AND `type` = 'ලෙමන්'; -- LAD: ලෙමන්
UPDATE `items` SET `type` = 'Bottle Gourd', `updated_at` = NOW() WHERE `id` = 288 AND `type` = 'ලබු'; -- LBU: ලබු
UPDATE `items` SET `type` = 'Onion Flowers', `updated_at` = NOW() WHERE `id` = 290 AND `type` = 'ලුනු මල්'; -- LUM: ලුනු මල්
UPDATE `items` SET `type` = 'Spring Onion', `updated_at` = NOW() WHERE `id` = 291 AND `type` = 'ලුනු ගස්'; -- LUN: ලුනු ගස්
UPDATE `items` SET `type` = 'Capsicum (Malu Miris)', `updated_at` = NOW() WHERE `id` = 292 AND `type` = 'මාලු මිරිස්'; -- MAA: මාලු මිරිස්
UPDATE `items` SET `type` = 'Long Beans', `updated_at` = NOW() WHERE `id` = 293 AND `type` = 'මෑ කරල්'; -- MAB: මෑ කරල්
UPDATE `items` SET `type` = 'Drumsticks (Bundles)', `updated_at` = NOW() WHERE `id` = 294 AND `type` = 'මුරුංගා මිටි'; -- MAC: මුරුංගා මිටි
UPDATE `items` SET `type` = 'Salad Cucumber', `updated_at` = NOW() WHERE `id` = 295 AND `type` = 'මාලු පිපිඤ්ඤා'; -- MAD: මාලු පිපිඤ්ඤා
UPDATE `items` SET `type` = 'Drumsticks (Bags)', `updated_at` = NOW() WHERE `id` = 296 AND `type` = 'මුරුංගා බෑග්'; -- MAE: මුරුංගා බෑග්
UPDATE `items` SET `type` = 'Mukunuwenna', `updated_at` = NOW() WHERE `id` = 297 AND `type` = 'මුකුණු වැන්න'; -- MAF: මුකුණු වැන්න
UPDATE `items` SET `type` = 'Grapes - Jaffna', `updated_at` = NOW() WHERE `id` = 298 AND `type` = 'මිදි-යාපනය'; -- MAG: මිදි-යාපනය
UPDATE `items` SET `type` = 'Cassava (Manioc)', `updated_at` = NOW() WHERE `id` = 299 AND `type` = 'මයියොක්කා'; -- MAI: මයියොක්කා
UPDATE `items` SET `type` = 'Long Beans (Bundles)', `updated_at` = NOW() WHERE `id` = 300 AND `type` = 'මෑකරල් මිටි'; -- MAL: මෑකරල් මිටි
UPDATE `items` SET `type` = 'Mangosteen', `updated_at` = NOW() WHERE `id` = 301 AND `type` = 'මැන්ගුස්'; -- MAN: මැන්ගුස්
UPDATE `items` SET `type` = 'Mimini Pumpkin', `updated_at` = NOW() WHERE `id` = 302 AND `type` = 'මිමිණි වට්ටක්කා'; -- MEI: මිමිණි වට්ටක්කා
UPDATE `items` SET `type` = 'Green Chilli', `updated_at` = NOW() WHERE `id` = 204 AND `type` = 'අමු මිරිස්'; -- MI: අමු මිරිස්
UPDATE `items` SET `type` = 'Mint', `updated_at` = NOW() WHERE `id` = 303 AND `type` = 'මින්චි'; -- MIN: මින්චි
UPDATE `items` SET `type` = 'Marrow', `updated_at` = NOW() WHERE `id` = 304 AND `type` = 'මැරෝ වට්ටක්කා'; -- MRW: මැරෝ වට්ටක්කා
UPDATE `items` SET `type` = 'Knol Khol', `updated_at` = NOW() WHERE `id` = 305 AND `type` = 'නෝකොල්'; -- NAA: නෝකොල්
UPDATE `items` SET `type` = 'Lotus Root', `updated_at` = NOW() WHERE `id` = 306 AND `type` = 'නෙලුම් අල'; -- NAB: නෙලුම් අල
UPDATE `items` SET `type` = 'Mandarin (Naran)', `updated_at` = NOW() WHERE `id` = 307 AND `type` = 'නාරං'; -- NAC: නාරං
UPDATE `items` SET `type` = 'Nil Athu', `updated_at` = NOW() WHERE `id` = 308 AND `type` = 'නිල් අතු'; -- NIL: නිල් අතු
UPDATE `items` SET `type` = 'Nai Miris (Hot Chilli)', `updated_at` = NOW() WHERE `id` = 309 AND `type` = 'නයිමිරිස්'; -- NIM: නයිමිරිස්
UPDATE `items` SET `type` = 'Spinach (Niwithi)', `updated_at` = NOW() WHERE `id` = 310 AND `type` = 'නිවිති'; -- NIW: නිවිති
UPDATE `items` SET `type` = 'Nelli (Gooseberry)', `updated_at` = NOW() WHERE `id` = 311 AND `type` = 'නෙල්ලි'; -- NIX: නෙල්ලි
UPDATE `items` SET `type` = 'Male', `updated_at` = NOW() WHERE `id` = 312 AND `type` = 'මැලේ'; -- O: මැලේ
UPDATE `items` SET `type` = 'Small Male', `updated_at` = NOW() WHERE `id` = 313 AND `type` = 'පොඩි මැලේ'; -- OP: පොඩි මැලේ
UPDATE `items` SET `type` = 'Cucumber', `updated_at` = NOW() WHERE `id` = 314 AND `type` = 'පිපිඤ්ඤා'; -- PAA: පිපිඤ්ඤා
UPDATE `items` SET `type` = 'Snake Gourd', `updated_at` = NOW() WHERE `id` = 315 AND `type` = 'පතෝල'; -- PAB: පතෝල
UPDATE `items` SET `type` = 'Keli Pathola (Small Snake Gourd)', `updated_at` = NOW() WHERE `id` = 316 AND `type` = 'කැලි පතෝල'; -- PAC: කැලි පතෝල
UPDATE `items` SET `type` = 'Guava', `updated_at` = NOW() WHERE `id` = 317 AND `type` = 'පේර'; -- PER: පේර
UPDATE `items` SET `type` = 'Ash Pumpkin', `updated_at` = NOW() WHERE `id` = 318 AND `type` = 'පුහුල්'; -- PHU: පුහුල්
UPDATE `items` SET `type` = 'Coconut', `updated_at` = NOW() WHERE `id` = 319 AND `type` = 'පොල්'; -- POO: පොල්
UPDATE `items` SET `type` = 'Polos (Young Jackfruit)', `updated_at` = NOW() WHERE `id` = 320 AND `type` = 'පොලෝස්'; -- PPL: පොලෝස්
UPDATE `items` SET `type` = 'Passion Fruit', `updated_at` = NOW() WHERE `id` = 321 AND `type` = 'පැශන්'; -- PSA: පැශන්
UPDATE `items` SET `type` = 'Puwalu Banana', `updated_at` = NOW() WHERE `id` = 322 AND `type` = 'පුවාලු කෙසෙල්'; -- PULL: පුවාලු කෙසෙල්
UPDATE `items` SET `type` = 'Arecanut', `updated_at` = NOW() WHERE `id` = 323 AND `type` = 'පුවක්'; -- PWA: පුවක්
UPDATE `items` SET `type` = 'Cash', `updated_at` = NOW() WHERE `id` = 324 AND `type` = 'මුදල්'; -- QQQ: මුදල්
UPDATE `items` SET `type` = 'Red Onion', `updated_at` = NOW() WHERE `id` = 325 AND `type` = 'රතු ලුනු'; -- RAA: රතු ලුනු
UPDATE `items` SET `type` = 'Radish', `updated_at` = NOW() WHERE `id` = 326 AND `type` = 'රාබු'; -- RAB: රාබු
UPDATE `items` SET `type` = 'Red Banana', `updated_at` = NOW() WHERE `id` = 327 AND `type` = 'රත් කෙසෙල්'; -- RAC: රත් කෙසෙල්
UPDATE `items` SET `type` = 'Rampe (Pandan)', `updated_at` = NOW() WHERE `id` = 328 AND `type` = 'රම්පෙ'; -- RAM: රම්පෙ
UPDATE `items` SET `type` = 'Rambutan', `updated_at` = NOW() WHERE `id` = 329 AND `type` = 'රඹුටන්'; -- RBU: රඹුටන්
UPDATE `items` SET `type` = 'Raja Ala (Greater Yam)', `updated_at` = NOW() WHERE `id` = 330 AND `type` = 'රාජ අල'; -- RJA: රාජ අල
UPDATE `items` SET `type` = 'Small Raja Ala', `updated_at` = NOW() WHERE `id` = 331 AND `type` = 'පොඩි රාජ අල'; -- RJB: පොඩි රාජ අල
UPDATE `items` SET `type` = 'Peanuts', `updated_at` = NOW() WHERE `id` = 332 AND `type` = 'රටකජූ'; -- RKU: රටකජූ
UPDATE `items` SET `type` = 'Ratala', `updated_at` = NOW() WHERE `id` = 333 AND `type` = 'රටල'; -- RTL: රටල
UPDATE `items` SET `type` = 'Lettuce (Salad)', `updated_at` = NOW() WHERE `id` = 334 AND `type` = 'සලාද'; -- SAL: සලාද
UPDATE `items` SET `type` = 'Sarana', `updated_at` = NOW() WHERE `id` = 335 AND `type` = 'සාරණ'; -- SAR: සාරණ
UPDATE `items` SET `type` = 'White Bitter Gourd', `updated_at` = NOW() WHERE `id` = 336 AND `type` = 'සුදු කරවිල'; -- SDD: සුදු කරවිල
UPDATE `items` SET `type` = 'Lemongrass', `updated_at` = NOW() WHERE `id` = 337 AND `type` = 'සේර'; -- SER: සේර
UPDATE `items` SET `type` = 'Seeni Banana', `updated_at` = NOW() WHERE `id` = 338 AND `type` = 'සිනී කෙසෙල්'; -- SIN: සිනී කෙසෙල්
UPDATE `items` SET `type` = 'Tamarind', `updated_at` = NOW() WHERE `id` = 339 AND `type` = 'සියඹලා'; -- SIY: සියඹලා
UPDATE `items` SET `type` = 'Garlic', `updated_at` = NOW() WHERE `id` = 340 AND `type` = 'සුදු ලුනු'; -- SSD: සුදු ලුනු
UPDATE `items` SET `type` = 'Tomato', `updated_at` = NOW() WHERE `id` = 341 AND `type` = 'තක්කාලි'; -- TAA: තක්කාලි
UPDATE `items` SET `type` = 'Thalana Batu', `updated_at` = NOW() WHERE `id` = 342 AND `type` = 'තලන බටු'; -- TAB: තලන බටු
UPDATE `items` SET `type` = 'Thibbatu', `updated_at` = NOW() WHERE `id` = 343 AND `type` = 'තිත්බටු'; -- TAC: තිත්බටු
UPDATE `items` SET `type` = 'Tomato - Bags/Boxes', `updated_at` = NOW() WHERE `id` = 344 AND `type` = 'තක්කාලි-මලු/පෙට්ටි'; -- TAD: තක්කාලි-මලු/පෙට්ටි
UPDATE `items` SET `type` = 'Thiyambara (Spiny Gourd)', `updated_at` = NOW() WHERE `id` = 345 AND `type` = 'තියඹරා'; -- TAE: තියඹරා
UPDATE `items` SET `type` = 'Thampala (Amaranth)', `updated_at` = NOW() WHERE `id` = 346 AND `type` = 'තම්පලා'; -- TAM: තම්පලා
UPDATE `items` SET `type` = 'King Coconut', `updated_at` = NOW() WHERE `id` = 347 AND `type` = 'තැඹිලි'; -- TBA: තැඹිලි
UPDATE `items` SET `type` = 'Thumba Karawila', `updated_at` = NOW() WHERE `id` = 348 AND `type` = 'තුඹ කරවිල'; -- THU: තුඹ කරවිල
UPDATE `items` SET `type` = 'Uguressa', `updated_at` = NOW() WHERE `id` = 349 AND `type` = 'උගුරැස්ස'; -- UGU: උගුරැස්ස
UPDATE `items` SET `type` = 'Wel Ala (Yam)', `updated_at` = NOW() WHERE `id` = 350 AND `type` = 'වැල් අල'; -- VAL: වැල් අල
UPDATE `items` SET `type` = 'Dried Bitter Gourd', `updated_at` = NOW() WHERE `id` = 351 AND `type` = 'වියලි කරවිල'; -- VKA: වියලි කරවිල
UPDATE `items` SET `type` = 'Brinjal', `updated_at` = NOW() WHERE `id` = 352 AND `type` = 'වම්බටු'; -- WAA: වම්බටු
UPDATE `items` SET `type` = 'Pumpkin', `updated_at` = NOW() WHERE `id` = 353 AND `type` = 'වට්ටක්කා'; -- WAB: වට්ටක්කා
UPDATE `items` SET `type` = 'Raja Pumpkin', `updated_at` = NOW() WHERE `id` = 354 AND `type` = 'රාජා වට්ටක්කා'; -- WAC: රාජා වට්ටක්කා
UPDATE `items` SET `type` = 'Brinjal (Panu)', `updated_at` = NOW() WHERE `id` = 355 AND `type` = 'වම්බටු-පණු'; -- WAD: වම්බටු-පණු
UPDATE `items` SET `type` = 'Small Pumpkin', `updated_at` = NOW() WHERE `id` = 356 AND `type` = 'පොඩි වට්ටක්කා'; -- WAE: පොඩි වට්ටක්කා
UPDATE `items` SET `type` = 'Ball Brinjal', `updated_at` = NOW() WHERE `id` = 357 AND `type` = 'බෝල බටු'; -- WAF: බෝල බටු
UPDATE `items` SET `type` = 'Purple Brinjal', `updated_at` = NOW() WHERE `id` = 358 AND `type` = 'දම්-වම්බටු'; -- WAG: දම්-වම්බටු
UPDATE `items` SET `type` = 'Leela Pumpkin', `updated_at` = NOW() WHERE `id` = 359 AND `type` = 'ලීලා වට්ටක්කා'; -- WAK: ලීලා වට්ටක්කා
UPDATE `items` SET `type` = 'Dried Chilli', `updated_at` = NOW() WHERE `id` = 360 AND `type` = 'වියලි මිරිස්'; -- WAS: වියලි මිරිස්
UPDATE `items` SET `type` = 'Ridged Gourd (Watakolu)', `updated_at` = NOW() WHERE `id` = 361 AND `type` = 'වැටකොලු'; -- WAT: වැටකොලු
UPDATE `items` SET `type` = 'Small Brinjal', `updated_at` = NOW() WHERE `id` = 362 AND `type` = 'පොඩි බටු'; -- WBB: පොඩි බටු
UPDATE `items` SET `type` = 'Veralu (Ceylon Olive)', `updated_at` = NOW() WHERE `id` = 363 AND `type` = 'වෙරලු'; -- WER: වෙරලු

-- 3. Check: should return no rows (any row listed still has a Sinhala name)
SELECT `id`, `no`, `type` FROM `items` WHERE `type` REGEXP '[අ-ෟ]';

-- To undo:
-- UPDATE `items` i JOIN `items_backup_20261115` b ON b.`id` = i.`id` SET i.`type` = b.`type`, i.`updated_at` = b.`updated_at`;
