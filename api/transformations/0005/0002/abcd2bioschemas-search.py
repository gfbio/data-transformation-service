# std
import sys
import json
# 3rd party
import saxonche as saxon
from saxonche import PySaxonProcessor
import xmltodict

def remove_duplicates(d):
    if isinstance(d, dict):
        for key, value in d.items():
            d[key] = remove_duplicates(value)
        return d
    elif isinstance(d, list):
        unique_list = list({json.dumps(item, sort_keys=False): item for item in d}.values())
        return [remove_duplicates(item) for item in unique_list]
    else:
        return d

def key_renaming(schema_dict, old_key, new_key):
    try:
        schema_dict['jsonld'][new_key] = schema_dict['jsonld'].pop(old_key)
    except KeyError:
        pass

    return schema_dict

def convert_values(schema_dict):
    try:
        schema_dict['jsonld']['isAccessibleForFree'] = bool(schema_dict['jsonld']['isAccessibleForFree'])
    except KeyError:
        pass
    
    try:
        schema_dict['jsonld']['size']['value'] = int(schema_dict['jsonld']['size']['value']['#text'])
    except KeyError:
        pass
    
    try:
        schema_dict['jsonld']['geo']['latitude'] = float(schema_dict['jsonld']['geo']['latitude']['#text'])
    except KeyError:
        pass
    
    try:
        schema_dict['jsonld']['geo']['longitude'] = float(schema_dict['jsonld']['geo']['longitude']['#text'])

    except KeyError:
        pass

    return schema_dict


def export_json(schema_dict):
    schema_json = json.dumps(schema_dict['jsonld'])

    return schema_json


def transform(xml, stylesheet_file):
    input_file = xml

    with PySaxonProcessor(license=False) as proc:
        xslt30proc = proc.new_xslt30_processor()

    try:
        schema_xml = xslt30proc.transform_to_string(source_file=input_file, stylesheet_file=stylesheet_file)
    except Exception as e:
        print(e)
        schema_xml = None

    try:
        schema_dict = xmltodict.parse(schema_xml)
    except Exception as e:
        print(e)
        schema_dict = None

    try:
        # rename keys to be JSON-LD compliant
        if schema_dict['jsonld'].get('reverse') is not None:
            schema_dict = key_renaming(schema_dict, 'reverse', '@reverse')
    except Exception as e:
        print(e)
        schema_dict = None

    try:
        schema_dict = remove_duplicates(schema_dict)
    except Exception as e:
        print(e)
        schema_dict = None

    try:
        schema_dict = convert_values(schema_dict)
    except Exception as e:
        print(e)
        schema_dict = None

    return export_json(schema_dict)

# call from php is 'python abcd2bioschemas-search.py $input_file $output_location $stylesheet_file'
def main():
    input_file = sys.argv[1]
    output_location = sys.argv[2]
    stylesheet_file = sys.argv[3]

    result = transform(input_file, stylesheet_file)

    with open(output_location, 'w') as f:
        f.write(result)

if __name__ == "__main__":
    main()