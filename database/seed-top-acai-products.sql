-- Produtos transcritos do cardápio Top Açaí Delivery.
-- A operação é idempotente: nomes já cadastrados permanecem sem alteração.
INSERT INTO products
    (name, description, category, price, image_url, is_featured, is_available, sort_order)
VALUES
    ('Açaí Premium Puro 250 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 11.60, '/top-acai-hero.svg', 0, 1, 10),
    ('Açaí Premium Puro 300 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 13.60, '/top-acai-hero.svg', 0, 1, 20),
    ('Açaí Premium Puro 400 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 15.00, '/top-acai-hero.svg', 0, 1, 30),
    ('Açaí Premium Puro 500 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 18.70, '/top-acai-hero.svg', 0, 1, 40),
    ('Açaí Premium Camadinha 250 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 14.86, '/top-acai-hero.svg', 0, 1, 110),
    ('Açaí Premium Camadinha 300 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 16.60, '/top-acai-hero.svg', 0, 1, 120),
    ('Açaí Premium Camadinha 400 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 19.60, '/top-acai-hero.svg', 0, 1, 130),
    ('Açaí Premium Camadinha 500 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 22.68, '/top-acai-hero.svg', 0, 1, 140),
    ('Açaí Premium Camadinha 700 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 28.60, '/top-acai-hero.svg', 0, 1, 150),
    ('Açaí Premium Camadinha 1000 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 38.98, '/top-acai-hero.svg', 0, 1, 160),
    ('Açaí Premium Especial 250 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 17.80, '/top-acai-hero.svg', 0, 1, 210),
    ('Açaí Premium Especial 300 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 22.64, '/top-acai-hero.svg', 0, 1, 220),
    ('Açaí Premium Especial 400 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 24.76, '/top-acai-hero.svg', 0, 1, 230),
    ('Açaí Premium Especial 500 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 28.89, '/top-acai-hero.svg', 0, 1, 240),
    ('Açaí Premium Especial 700 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 35.90, '/top-acai-hero.svg', 0, 1, 250),
    ('Açaí Premium Especial 1000 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 48.89, '/top-acai-hero.svg', 0, 1, 260),
    ('Batidinha de Açaí Premium 300 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 15.89, '/top-acai-hero.svg', 0, 1, 310),
    ('Batidinha de Açaí Premium 400 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 18.89, '/top-acai-hero.svg', 0, 1, 320),
    ('Batidinha de Açaí Premium 500 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 20.89, '/top-acai-hero.svg', 0, 1, 330),
    ('Batidinha de Açaí Premium 1000 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 30.89, '/top-acai-hero.svg', 0, 1, 340)
ON DUPLICATE KEY UPDATE name = VALUES(name);
