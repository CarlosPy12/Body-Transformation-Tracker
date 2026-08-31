-- Manutenzione: individua e rimuove misurazioni corporee duplicate.
-- Duplicato = stesso utente, stesso giorno e stessi valori di composizione corporea.
-- Cambia @user_id se devi operare su un utente diverso.

SET @user_id := 1;

-- 1) Preview: gruppi duplicati. Esegui prima solo questa SELECT se vuoi controllare.
SELECT
  DATE(measured_at) AS measured_date,
  weight_kg,
  bmi,
  body_fat,
  body_water,
  muscle,
  bone,
  left_arm_body_fat,
  left_arm_muscle,
  right_arm_body_fat,
  right_arm_muscle,
  left_leg_body_fat,
  left_leg_muscle,
  right_leg_body_fat,
  right_leg_muscle,
  trunk_body_fat,
  trunk_muscle,
  metabolic_age,
  heart_rate_bpm,
  visceral_fat,
  COUNT(*) AS duplicate_count,
  GROUP_CONCAT(id ORDER BY measured_at, id) AS ids
FROM body_measurements
WHERE user_id = @user_id
GROUP BY
  user_id,
  DATE(measured_at),
  weight_kg,
  bmi,
  body_fat,
  body_water,
  muscle,
  bone,
  left_arm_body_fat,
  left_arm_muscle,
  right_arm_body_fat,
  right_arm_muscle,
  left_leg_body_fat,
  left_leg_muscle,
  right_leg_body_fat,
  right_leg_muscle,
  trunk_body_fat,
  trunk_muscle,
  metabolic_age,
  heart_rate_bpm,
  visceral_fat
HAVING COUNT(*) > 1
ORDER BY measured_date;

-- 2) Pulizia: lascia la prima riga del gruppo e cancella le copie successive.
START TRANSACTION;

CREATE TEMPORARY TABLE duplicate_body_measurement_ids AS
SELECT DISTINCT bm.id
FROM body_measurements bm
JOIN body_measurements keeper
  ON keeper.user_id = bm.user_id
  AND DATE(keeper.measured_at) = DATE(bm.measured_at)
  AND keeper.weight_kg <=> bm.weight_kg
  AND keeper.bmi <=> bm.bmi
  AND keeper.body_fat <=> bm.body_fat
  AND keeper.body_water <=> bm.body_water
  AND keeper.muscle <=> bm.muscle
  AND keeper.bone <=> bm.bone
  AND keeper.left_arm_body_fat <=> bm.left_arm_body_fat
  AND keeper.left_arm_muscle <=> bm.left_arm_muscle
  AND keeper.right_arm_body_fat <=> bm.right_arm_body_fat
  AND keeper.right_arm_muscle <=> bm.right_arm_muscle
  AND keeper.left_leg_body_fat <=> bm.left_leg_body_fat
  AND keeper.left_leg_muscle <=> bm.left_leg_muscle
  AND keeper.right_leg_body_fat <=> bm.right_leg_body_fat
  AND keeper.right_leg_muscle <=> bm.right_leg_muscle
  AND keeper.trunk_body_fat <=> bm.trunk_body_fat
  AND keeper.trunk_muscle <=> bm.trunk_muscle
  AND keeper.metabolic_age <=> bm.metabolic_age
  AND keeper.heart_rate_bpm <=> bm.heart_rate_bpm
  AND keeper.visceral_fat <=> bm.visceral_fat
  AND (
    keeper.measured_at < bm.measured_at
    OR (keeper.measured_at = bm.measured_at AND keeper.id < bm.id)
  )
WHERE bm.user_id = @user_id;

SELECT COUNT(*) AS rows_to_delete FROM duplicate_body_measurement_ids;

DELETE bm
FROM body_measurements bm
JOIN duplicate_body_measurement_ids d ON d.id = bm.id;

COMMIT;

-- 3) Verifica: deve tornare 0.
SELECT COUNT(*) AS remaining_duplicate_groups
FROM (
  SELECT 1
  FROM body_measurements
  WHERE user_id = @user_id
  GROUP BY
    user_id,
    DATE(measured_at),
    weight_kg,
    bmi,
    body_fat,
    body_water,
    muscle,
    bone,
    left_arm_body_fat,
    left_arm_muscle,
    right_arm_body_fat,
    right_arm_muscle,
    left_leg_body_fat,
    left_leg_muscle,
    right_leg_body_fat,
    right_leg_muscle,
    trunk_body_fat,
    trunk_muscle,
    metabolic_age,
    heart_rate_bpm,
    visceral_fat
  HAVING COUNT(*) > 1
) duplicate_groups;
