#!/bin/bash

TMP_XML_FILE=xml_full_description
ORIG_INFO_XML_FILE=../../appinfo/info.xml
DEST_INFO_XML_FILE=info.xml

rm -f $TMP_XML_FILE $INFO_XML_FILE

for d in */ ; do
    lang=`echo "$d" | sed 's/fr_FR/fr/g' | sed 's/es_ES/es/g' | sed 's/de_DE/de/g' | sed 's/ja_JP/ja/g' | sed 's/ru_RU/ru/g' | sed 's/nl_NL/nl/g' | sed 's/it_IT/it/g' | sed 's/da_DK/da/g' | sed 's/sv_SE/sv/g' | sed 's/tr_TR/tr/g' | sed 's/ko_KR/ko/g' | sed 's/ca_ES/ca/g' | sed 's/ro_RO/ro/g' | sed 's/no_NO/no/g' | sed 's/cs_CZ/cs/g' | sed 's/fi_FI/fi/g' | sed 's/hu_HU/hu/g' | sed 's/pl_PL/pl/g' | sed 's/sk_SK/sk/g' | sed 's/fa_IR/fa/g' | sed 's/hi_IN/hi/g' | sed 's/id_ID/id/g' | sed 's/uk_UA/uk/g' | sed 's/el_GR/el/g' | sed 's/bg_BG/bg/g' | sed 's/en_US/en/g' | sed 's/sl_SI/sl/g' | sed 's|/||g'`


    echo -n '    <summary lang="'$lang'">' >> $TMP_XML_FILE
    # grumpf
    cat $d/short_description.txt | tr '\n' ' ' | sed 's/ $//' >> $TMP_XML_FILE
    echo '</summary>' >> $TMP_XML_FILE

    echo '    <description lang="'$lang'">' >> $TMP_XML_FILE
    cat $d/full_description.md >> $TMP_XML_FILE
    echo >> $TMP_XML_FILE
    echo '    </description>' >> $TMP_XML_FILE
done

perl -pe 's/\s+<summary>.*<\/description>/`cat '$TMP_XML_FILE'`/e' $ORIG_INFO_XML_FILE > $DEST_INFO_XML_FILE

rm -f $TMP_XML_FILE
